<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Storage;
use Aws\Lambda\LambdaClient;
use Aws\Exception\AwsException;

class DesignPdfServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton('design.pdf', function ($app) {
            return new DesignPdfGenerator(
                config('services.aws.key'),
                config('services.aws.secret'),
                config('services.aws.region'),
                config('services.aws.lambda_function_pdf')
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}

/**
 * Class for generating PDFs from design data using AWS Lambda
 */
class DesignPdfGenerator
{
    protected $lambdaClient;
    protected $lambdaFunction;
    
    /**
     * Constructor
     * 
     * @param string $awsKey AWS API Key
     * @param string $awsSecret AWS API Secret
     * @param string $awsRegion AWS Region
     * @param string $lambdaFunction Lambda function name
     */
    public function __construct($awsKey, $awsSecret, $awsRegion, $lambdaFunction)
    {
        // check if the namespace Aws exists, log it if it does, then check if the namespace Aws\Lambda exists, log it if it does, then check if the class LambdaClient exists, log it if it does
        Storage::disk('local')->append('pdf.txt', "Checking if namespace Aws exists");
        if (class_exists('Aws')) {
            Storage::disk('local')->append('pdf.txt', "Namespace Aws exists");
        } else {
            Storage::disk('local')->append('pdf.txt', "Namespace Aws does not exist");
        }
        if (class_exists('Aws\Lambda')) {
            Storage::disk('local')->append('pdf.txt', "Namespace Aws\Lambda exists");
        } else {
            Storage::disk('local')->append('pdf.txt', "Namespace Aws\Lambda does not exist");
        }
        if (class_exists('Aws\Lambda\LambdaClient')) {
            Storage::disk('local')->append('pdf.txt', "Class Aws\Lambda\LambdaClient exists");
        } else {
            Storage::disk('local')->append('pdf.txt', "Class Aws\Lambda\LambdaClient does not exist");
        }
        // Initialize log file
        // Storage::disk('local')->append('pdf.txt', "=== PDF Generation Log Started at " . date('Y-m-d H:i:s') . " ===\n");
        Storage::disk('local')->append('pdf.txt', "Initializing DesignPdfGenerator with region: {$awsRegion}, function: {$lambdaFunction}");
        
        $this->lambdaFunction = $lambdaFunction;
        
        $this->lambdaClient = new LambdaClient([
            'version' => 'latest',
            'region' => $awsRegion,
            'credentials' => [
                'key' => $awsKey,
                'secret' => $awsSecret,
            ],
        ]);
        
        Storage::disk('local')->append('pdf.txt', "Lambda client initialized successfully");
    }
    
    /**
     * Generate a PDF from design elements and product specifications using Lambda
     *
     * @param array $elements Design elements
     * @param object $product Product details including dimensions and bleed
     * @param string $designName Name of the design for the PDF title
     * @return string Path to the generated PDF file
     */
    public function generatePdf($elements, $product, $designName = 'Design')
    {
        Storage::disk('local')->append('pdf.txt', "\n=== Standard PDF Generation Started ===");
        Storage::disk('local')->append('pdf.txt', "Design Name: {$designName}");
        Storage::disk('local')->append('pdf.txt', "Product: " . json_encode([
            'name' => $product->name,
            'finished_width' => $product->finished_width,
            'finished_length' => $product->finished_length,
            'bleed' => $product->bleed ?? 0,
        ]));
        Storage::disk('local')->append('pdf.txt', "Elements count: " . count($elements));
        
        // Prepare the data payload for Lambda
        $payload = [
            'type' => 'standard',
            'elements' => $elements,
            'product' => [
                'name' => $product->name,
                'finished_width' => $product->finished_width,
                'finished_length' => $product->finished_length,
                'bleed' => $product->bleed ?? 0,
            ],
            'design_name' => $designName,
        ];
        
        // Invoke Lambda function
        Storage::disk('local')->append('pdf.txt', "Invoking Lambda function for standard PDF");
        $pdfData = $this->invokeLambda($payload);
        
        // Generate a unique filename
        $filename = 'designs/pdf/' . uniqid() . '.pdf';
        Storage::disk('local')->append('pdf.txt', "Generated filename: {$filename}");
        
        // Store the PDF
        Storage::disk('local')->append('pdf.txt', "Storing PDF file, size: " . strlen($pdfData) . " bytes");
        Storage::disk('public')->put($filename, $pdfData);
        
        Storage::disk('local')->append('pdf.txt', "Standard PDF generation completed successfully");
        return $filename;
    }
    
    /**
     * Generate a print-ready PDF with crop marks and metadata using Lambda
     *
     * @param array $elements Design elements
     * @param object $product Product details including dimensions and bleed
     * @param string $designName Name of the design
     * @param array $metadata Additional printing metadata (optional)
     * @return string Path to the generated print-ready PDF file
     */
    public function generatePrintReadyPdf($elements, $product, $designName, $metadata = [])
    {
        Storage::disk('local')->append('pdf.txt', "\n=== Print-Ready PDF Generation Started ===");
        Storage::disk('local')->append('pdf.txt', "Design Name: {$designName}");
        Storage::disk('local')->append('pdf.txt', "Product: " . json_encode([
            'name' => $product->name,
            'finished_width' => $product->finished_width,
            'finished_length' => $product->finished_length,
            'bleed' => $product->bleed ?? 0,
        ]));
        Storage::disk('local')->append('pdf.txt', "Elements count: " . count($elements));
        Storage::disk('local')->append('pdf.txt', "Metadata: " . json_encode($metadata));
        
        // Prepare the data payload for Lambda
        $payload = [
            'type' => 'print_ready',
            'elements' => $elements,
            'product' => [
                'name' => $product->name,
                'finished_width' => $product->finished_width,
                'finished_length' => $product->finished_length,
                'bleed' => $product->bleed ?? 0,
            ],
            'design_name' => $designName,
            'metadata' => $metadata,
        ];
        
        // Invoke Lambda function
        Storage::disk('local')->append('pdf.txt', "Invoking Lambda function for print-ready PDF");
        $pdfData = $this->invokeLambda($payload);
        
        // Generate a unique filename
        $filename = 'designs/print-ready/' . uniqid() . '.pdf';
        Storage::disk('local')->append('pdf.txt', "Generated filename: {$filename}");
        
        // Store the PDF
        Storage::disk('local')->append('pdf.txt', "Storing PDF file, size: " . strlen($pdfData) . " bytes");
        Storage::disk('public')->put($filename, $pdfData);
        
        Storage::disk('local')->append('pdf.txt', "Print-ready PDF generation completed successfully");
        return $filename;
    }
    
    /**
     * Invoke Lambda function and return PDF binary data
     * 
     * @param array $payload Data to send to Lambda
     * @return string Binary PDF data
     * @throws \Exception If Lambda invocation fails
     */
    protected function invokeLambda($payload)
    {
        Storage::disk('local')->append('pdf.txt', "Lambda invocation started");
        Storage::disk('local')->append('pdf.txt', "Function name: {$this->lambdaFunction}");
        Storage::disk('local')->append('pdf.txt', "Payload size: " . strlen(json_encode($payload)) . " bytes");
        
        try {
            // Invoke Lambda function synchronously
            Storage::disk('local')->append('pdf.txt', "Sending request to AWS Lambda");
            $startTime = microtime(true);
            
            $result = $this->lambdaClient->invoke([
                'FunctionName' => $this->lambdaFunction,
                'Payload' => json_encode($payload),
            ]);
            
            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000, 2);
            Storage::disk('local')->append('pdf.txt', "Lambda execution time: {$executionTime}ms");
            
            // Get the response payload
            Storage::disk('local')->append('pdf.txt', "Response received, status code: {$result['StatusCode']}");
            $response = json_decode($result['Payload']->getContents(), true);
            
            // Check for errors
            if (isset($response['errorMessage'])) {
                Storage::disk('local')->append('pdf.txt', "ERROR: Lambda returned error: {$response['errorMessage']}");
                throw new \Exception('Lambda error: ' . $response['errorMessage']);
            }
            
            // The PDF should be returned as a base64-encoded string
            if (!isset($response['pdf_data'])) {
                Storage::disk('local')->append('pdf.txt', "ERROR: No PDF data returned from Lambda");
                throw new \Exception('No PDF data returned from Lambda');
            }
            
            $pdfDataSize = strlen($response['pdf_data']);
            Storage::disk('local')->append('pdf.txt', "Received base64 PDF data, size: {$pdfDataSize} bytes");
            
            // Decode the base64-encoded PDF
            $decodedPdf = base64_decode($response['pdf_data']);
            Storage::disk('local')->append('pdf.txt', "Decoded PDF size: " . strlen($decodedPdf) . " bytes");
            Storage::disk('local')->append('pdf.txt', "Lambda invocation completed successfully");
            
            return $decodedPdf;
            
        } catch (AwsException $e) {
            Storage::disk('local')->append('pdf.txt', "CRITICAL ERROR: AWS Lambda Exception: " . $e->getMessage());
            Storage::disk('local')->append('pdf.txt', "Error code: " . $e->getAwsErrorCode());
            Storage::disk('local')->append('pdf.txt', "Error type: " . $e->getAwsErrorType());
            Storage::disk('local')->append('pdf.txt', "Request ID: " . $e->getAwsRequestId());
            \Log::error('AWS Lambda Error: ' . $e->getMessage());
            throw new \Exception('Failed to generate PDF: ' . $e->getMessage());
        } catch (\Exception $e) {
            Storage::disk('local')->append('pdf.txt', "CRITICAL ERROR: General Exception: " . $e->getMessage());
            Storage::disk('local')->append('pdf.txt', "Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }
}