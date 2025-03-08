<?php

namespace App\Jobs;

use App\Models\Design;
use App\Models\PdfJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Providers\DesignPdfServiceProvider;
use Illuminate\Support\Facades\Storage;

class GeneratePdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The PDF job instance.
     *
     * @var \App\Models\PdfJob
     */
    protected $pdfJob;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 2;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 2;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(PdfJob $pdfJob)
    {
        $this->pdfJob = $pdfJob;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Storage::append('pdf2.txt', 'Starting PDF generation job: ' . $this->pdfJob->id);
        
        try {
            // Mark job as processing
            $this->pdfJob->update(['status' => 'processing']);
            Storage::append('pdf2.txt', 'Job marked as processing');
            
            // Get design data
            $design = Design::with('product')->findOrFail($this->pdfJob->design_id);
            Storage::append('pdf2.txt', 'Design loaded: ' . $design->id . ' - ' . $design->name);
            
            // Generate PDF using the service provider
            $pdfGenerator = app('design.pdf');
            Storage::append('pdf2.txt', 'PDF generator initialized');
            
            // Generate PDF based on the requested type
            if ($this->pdfJob->type === 'print_ready') {
                Storage::append('pdf2.txt', 'Generating print-ready PDF');
                $pdfPath = $pdfGenerator->generatePrintReadyPdf(
                    $design->elements,
                    $design->product,
                    $design->name
                );
                Storage::append('pdf2.txt', 'Print-ready PDF generated at: ' . $pdfPath);
            } else {
                Storage::append('pdf2.txt', 'Generating standard PDF');
                $pdfPath = $pdfGenerator->generatePdf(
                    $design->elements,
                    $design->product,
                    $design->name
                );
                Storage::append('pdf2.txt', 'Standard PDF generated at: ' . $pdfPath);
            }
            
            // Update job with success status and file path
            $this->pdfJob->update([
                'status' => 'completed',
                'file_path' => $pdfPath
            ]);
            Storage::append('pdf2.txt', 'Job marked as completed');
            
        } catch (\Exception $e) {
            Storage::append('pdf2.txt', 'ERROR: PDF Generation failed: ' . $e->getMessage());
            
            Log::error('PDF Generation failed: ' . $e->getMessage(), [
                'job_id' => $this->pdfJob->job_id,
                'design_id' => $this->pdfJob->design_id,
                'exception' => $e
            ]);
            
            // Update job with error status
            $this->pdfJob->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);
            Storage::append('pdf2.txt', 'Job marked as failed');
            
            // Re-throw the exception if this is not the final attempt
            if ($this->attempts() < $this->tries) {
                Storage::append('pdf2.txt', 'Retrying job. Attempt: ' . $this->attempts() . ' of ' . $this->tries);
                throw $e;
            }
        }
    }
    
    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        Storage::append('pdf2.txt', 'Job failed completely after all attempts. Error: ' . $exception->getMessage());
        
        // Update job with failed status
        $this->pdfJob->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage()
        ]);
    }
}