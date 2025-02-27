<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnthropicServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton('anthropic', function ($app) {
            return new AnthropicClient(
                config('services.anthropic.api_key'),
                config('services.anthropic.model', 'claude-3-haiku-20240307')
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
 * Client for interacting with the Anthropic API
 */
class AnthropicClient
{
    protected $apiKey;
    protected $apiUrl = 'https://api.anthropic.com/v1/messages';
    protected $model;
    
    public function __construct($apiKey, $model)
    {
        $this->apiKey = $apiKey;
        $this->model = $model;
    }
    
    /**
     * Send a message to the Anthropic API
     *
     * @param array $messages Previous conversation messages
     * @param string $systemPrompt System prompt to guide Claude
     * @return array|null Response from Claude or null on error
     */
    public function sendMessage(array $messages, string $systemPrompt = null)
    {
        try {
            // Format messages for Anthropic API
            $formattedMessages = $this->formatMessages($messages);
            
            // Prepare request data
            $requestData = [
                'model' => $this->model,
                'messages' => $formattedMessages,
                'max_tokens' => 1000
            ];
            
            // Add system prompt if provided
            if ($systemPrompt) {
                $requestData['system'] = $systemPrompt;
            }
            
            // Make the API request
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json'
            ])->post($this->apiUrl, $requestData);
            
            // Check for errors
            if (!$response->successful()) {
                Log::error('Anthropic API error: ' . $response->body());
                return null;
            }
            
            return $response->json();
            
        } catch (\Exception $e) {
            Log::error('Error calling Anthropic API: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Format messages for the Anthropic API
     *
     * @param array $messages
     * @return array
     */
    protected function formatMessages(array $messages)
    {
        $formattedMessages = [];
        
        foreach ($messages as $message) {
            $formattedMessages[] = [
                'role' => $message['role'],
                'content' => $message['content']
            ];
        }
        
        return $formattedMessages;
    }
}