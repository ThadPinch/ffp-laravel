<?php

namespace App\Http\Controllers;

use App\Models\Design;
use App\Models\Product;
use App\Models\DesignVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Intervention\Image\Facades\Image;
use App\Providers\DesignPdfServiceProvider;
use PDF;
use App\Models\PdfJob;
use Illuminate\Support\Str;

class DesignController extends Controller
{
    // Show create design page
    public function create()
    {
        return Inertia::render('Design', [
            'products' => Product::all()
        ]);
    }
    
    // Edit an existing design
    public function edit(Design $design)
    {
        // Check if user is the owner of the design
        if ($design->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }
        
        return Inertia::render('Design', [
            'products' => Product::all(),
            'selectedProductId' => $design->product_id,
            'design' => $design->load('product')
        ]);
    }
    
    // API endpoint to save a design
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'elements' => 'required|array',
            'name' => 'nullable|string|max:255',
        ]);
        
        // Create design
        $design = Design::create([
            'user_id' => Auth::id(),
            'product_id' => $request->product_id,
            'elements' => $request->elements,
            'name' => $request->name ?? 'Untitled Design',
        ]);
        
        // Generate and save thumbnail if image data provided
        if ($request->has('thumbnail_data')) {
            $this->saveThumbnail($design, $request->thumbnail_data);
        }
        
        // Create initial version
        $design->createVersion('Initial version');
        
        return response()->json($design, 201);
    }
    
    // API endpoint to update a design
    public function update(Request $request, Design $design)
    {
        // Check if user is the owner of the design
        if ($design->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $request->validate([
            'elements' => 'required|array',
            'name' => 'nullable|string|max:255',
            'create_version' => 'nullable|boolean',
            'version_comment' => 'nullable|string|max:255',
        ]);
        
        // Check if elements have changed and we should create a version
        $elementsChanged = json_encode($design->elements) !== json_encode($request->elements);
        $shouldCreateVersion = $request->input('create_version', false) && $elementsChanged;
        
        // Update design
        $design->elements = $request->elements;
        if ($request->has('name')) {
            $design->name = $request->name;
        }
        $design->save();
        
        // Generate and save thumbnail if image data provided
        if ($request->has('thumbnail_data')) {
            $this->saveThumbnail($design, $request->thumbnail_data);
        }
        
        // Create a new version if requested
        if ($shouldCreateVersion) {
            $design->createVersion($request->input('version_comment', 'Update'));
        }
        
        return response()->json($design);
    }
    
    // API endpoint to list designs for current user
    public function index(Request $request)
    {
        $query = Design::where('user_id', Auth::id())
            ->with('product');
            
        // Add filter by product_id if provided
        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }
        
        $designs = $query->orderBy('updated_at', 'desc')
            ->paginate(12);
            
        return response()->json($designs);
    }
    
    // API endpoint to get a single design with all details
    public function show(Design $design)
    {
        // Check if user is the owner or the design is a template
        if ($design->user_id !== Auth::id() && !($design->is_template ?? false)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        // Load associated product
        $design->load('product');
        
        return response()->json($design);
    }
    
    // API endpoint to get versions of a design
    public function versions(Design $design)
    {
        // Check if user is the owner of the design
        if ($design->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $versions = $design->versions()
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json($versions);
    }
    
    // API endpoint to restore a specific version
    public function restoreVersion(Design $design, DesignVersion $version)
    {
        // Check if user is the owner of the design
        if ($design->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        // Create a new version first to save the current state
        $design->createVersion('Auto-saved before restoring version #' . $version->id);
        
        // Restore from the selected version
        $design->elements = $version->elements;
        $design->save();
        
        return response()->json($design);
    }
    
    // Helper method to save thumbnail
    private function saveThumbnail(Design $design, $imageData)
    {
        // Extract base64 image data
        $imageData = substr($imageData, strpos($imageData, ',') + 1);
        $imageData = base64_decode($imageData);
        
        // Create image and resize
        $img = Image::make($imageData);
        $img->resize(400, null, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });
        
        // Save thumbnail
        $path = 'designs/thumbnails/' . $design->id . '.jpg';
        Storage::disk('public')->put($path, (string) $img->encode('jpg', 80));
        
        // Update design with thumbnail path
        $design->thumbnail = $path;
        $design->save();
        
        return $path;
    }
    
    // Delete a design
    public function destroy(Design $design)
    {
        // Check if user is the owner of the design
        if ($design->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        // Delete associated thumbnail
        if ($design->thumbnail) {
            Storage::disk('public')->delete($design->thumbnail);
        }
        
        // Delete design (versions will cascade delete)
        $design->delete();
        
        return response()->noContent();
    }
    
  /**
     * Initialize PDF generation with Lambda
     * This is an asynchronous process that will be completed in the background
     */
    public function generatePdf(Request $request, Design $design)
    {
        // Check if user is the owner of the design
        if ($design->user_id !== Auth::id() && !($design->is_template ?? false)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        // Load associated product
        $design->load('product');
        
        $request->validate([
            'designType' => 'required|string|in:standard,print_ready',
        ]);

        Storage::append('pdf2.txt', 'Generating PDF for design: ' . $design->id);
        // Create a job record to track the PDF generation
        $job = PdfJob::create([
            'user_id' => Auth::id(),
            'design_id' => $design->id,
            'type' => $request->designType,
            'status' => 'pending',
            'job_id' => (string) Str::uuid(), // Generate a unique job ID
        ]);
        
        // Dispatch the PDF generation job to the queue
        dispatch(new \App\Jobs\GeneratePdfJob($job));
        Storage::append('pdf2.txt', 'Job dispatched');
        
        return response()->json([
            'job_id' => $job->job_id,
            'message' => 'PDF generation has been initiated'
        ]);
    }
    
    /**
     * Check the status of a PDF generation job
     */
    public function checkPdfStatus(Request $request, string $jobId)
    {
        $job = PdfJob::where('job_id', $jobId)
            ->where('user_id', Auth::id())
            ->firstOrFail();
        
        $response = [
            'status' => $job->status,
        ];
        
        if ($job->status === 'completed') {
            $response['download_url'] = url("storage/{$job->file_path}");
        } elseif ($job->status === 'failed') {
            $response['error'] = $job->error_message;
        }
        
        return response()->json($response);
    }
    
    /**
     * Get the actual PDF file (if the generation is complete)
     */
    public function downloadPdfFile(string $jobId)
    {
        $job = PdfJob::where('job_id', $jobId)
            ->where('user_id', Auth::id())
            ->firstOrFail();
        
        if ($job->status !== 'completed' || !$job->file_path) {
            abort(404, 'PDF file not found or generation not complete');
        }
        
        // Get the design details for the filename
        $design = Design::findOrFail($job->design_id);
        
        // Create a readable filename
        $filename = Str::slug($design->name) . '_' . ($job->type === 'print_ready' ? 'print-ready' : 'standard') . '.pdf';
        
        return response()->download(
            storage_path("app/public/{$job->file_path}"),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }
}