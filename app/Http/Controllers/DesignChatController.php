<?php

namespace App\Http\Controllers;

use App\Models\Design;
use App\Models\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DesignChatController extends Controller
{
    /**
     * Get the Anthropic client instance
     */
    protected function anthropic()
    {
        return app('anthropic');
    }
    
    /**
     * Get all chat messages for a design
     */
    public function getMessages(Request $request, Design $design)
    {
        // Check if user has access to this design
        if ($design->user_id !== Auth::id() && !$design->is_template) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $messages = $design->chatMessages()
            ->orderBy('created_at')
            ->get()
            ->map(function ($message) {
                return [
                    'id' => $message->id,
                    'role' => $message->role,
                    'content' => $message->content,
                    'created_at' => $message->created_at
                ];
            });
        
        return response()->json($messages);
    }
    
    /**
     * Send a message to the AI and get a response
     */
    public function sendMessage(Request $request, Design $design)
    {
        // Validate request
        $request->validate([
            'message' => 'required|string',
        ]);
        
        // Check if user has access to this design
        if ($design->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        try {
            // Save user message to database
            $userMessage = $design->chatMessages()->create([
                'user_id' => Auth::id(),
                'role' => 'user',
                'content' => $request->message
            ]);
            
            // Get previous messages for context (limit to last 10 for simplicity)
            $previousMessages = $design->chatMessages()
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($message) {
                    return [
                        'role' => $message->role,
                        'content' => $message->content
                    ];
                })
                ->sortBy(function ($message, $key) {
                    return $key;
                })
                ->values()
                ->toArray();
            
            // Create system prompt with design context and JSON response format instruction
            $systemPrompt = $this->createSystemPrompt($design);
            
            // Call Anthropic API
            $response = $this->anthropic()->sendMessage($previousMessages, $systemPrompt);
            
            if (!$response || !isset($response['content'][0]['text'])) {
                throw new \Exception('Invalid response from Anthropic API');
            }
            
            $assistantMessage = $response['content'][0]['text'];
            
            // Parse the AI response to see if there are design changes to make
            $designChanges = $this->parseDesignChanges($assistantMessage, $design->elements);
            
            // Save AI response to database
            $aiMessage = $design->chatMessages()->create([
                'user_id' => null,
                'role' => 'assistant',
                'content' => $assistantMessage,
                'metadata' => [
                    'has_changes' => !empty($designChanges),
                    'model' => $response['model'] ?? null
                ]
            ]);
            
            // Apply design changes if any
            $elementsUpdated = false;
            if (!empty($designChanges)) {
                $design->elements = $designChanges;
                $design->save();
                $elementsUpdated = true;
                
                // Create a new version to track the changes
                $design->createVersion('AI-assisted changes');
            }
            
            return response()->json([
                'message' => [
                    'id' => $aiMessage->id,
                    'role' => 'assistant',
                    'content' => $assistantMessage,
                    'created_at' => $aiMessage->created_at
                ],
                'elements_updated' => $elementsUpdated,
                'elements' => $elementsUpdated ? $design->elements : null
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in design chat: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Sorry, there was an error processing your request. Please try again.'
            ], 500);
        }
    }
    
    /**
     * Create a system prompt with design context
     */
    protected function createSystemPrompt(Design $design)
    {
        $product = $design->product;
        $currentElements = json_encode($design->elements);
        
        return <<<EOT
You are a helpful design assistant for a design customization platform. Your job is to help users modify their designs.

The user is working on a design for: {$product->name}
The dimensions are: {$product->finished_width}" x {$product->finished_length}"

The current design has the following elements:
$currentElements

When the user asks to modify the design (like adding text, changing colors, moving elements, etc.), 
analyze their request and suggest specific changes to the elements array. 

IMPORTANT: You must ALWAYS return your response in valid JSON format with exactly two properties:
1. "message": A conversational message explaining the changes you've made or your response to the user.
2. "design": The complete elements array with any changes applied. If no changes were requested or needed, return the original elements array.

Example JSON format:
{
  "message": "I've added a title at the top of your design with the text 'Hello World'.",
  "design": [
    {...existing element...},
    {...existing element...},
    {...new or modified element...}
  ]
}

Follow these guidelines:
1. Be helpful and respond conversationally in the "message" field
2. If the user asks for a design change, explain what you're doing
3. Only modify the design when specifically asked to
4. Don't make assumptions about elements that don't exist
5. Consider standard design principles when making suggestions
6. ALWAYS include the complete elements array in the "design" field, including any unchanged elements
7. Make sure your response is valid JSON that can be parsed

Your response will be processed automatically to update the design, so ensure your JSON is properly formatted.
EOT;
    }
    
    /**
     * Parse the AI response to extract design changes
     * This is a simplified implementation - in a real app, you'd want more robust parsing
     */
    protected function parseDesignChanges($response, $currentElements)
    {
        try {
            // Try to parse the response as JSON
            $parsedResponse = json_decode($response, true);
            
            // If successfully parsed and contains a design field with elements
            if (
                $parsedResponse && 
                is_array($parsedResponse) && 
                isset($parsedResponse['design']) && 
                is_array($parsedResponse['design']) && 
                !empty($parsedResponse['design'])
            ) {
                return $parsedResponse['design'];
            }
        } catch (\Exception $e) {
            Log::error('Error parsing design changes JSON: ' . $e->getMessage());
        }
        
        // Fallback to the original parsing method if JSON parsing fails
        $hasAddText = preg_match('/add(ed)? (a )?text/i', $response);
        $hasAddShape = preg_match('/add(ed)? (a )?shape/i', $response);
        $hasChangeColor = preg_match('/chang(ed|ing) (the )?color/i', $response);
        $hasResize = preg_match('/resiz(ed|ing)|made (it )?(larger|smaller|bigger)/i', $response);
        $hasMove = preg_match('/mov(ed|ing)|position(ed|ing)/i', $response);
        
        if ($hasAddText || $hasAddShape || $hasChangeColor || $hasResize || $hasMove) {
            $designAIController = app(DesignAIController::class);
            
            $mockRequest = new Request([
                'prompt' => $response,
                'currentElements' => $currentElements,
                'productInfo' => [
                    'id' => $design->product_id,
                    'finished_width' => $design->product->finished_width,
                    'finished_length' => $design->product->finished_length
                ]
            ]);
            
            try {
                $result = $designAIController->edit($mockRequest);
                $content = json_decode($result->getContent(), true);
                
                if (isset($content['elements'])) {
                    return $content['elements'];
                }
            } catch (\Exception $e) {
                Log::error('Error parsing design changes: ' . $e->getMessage());
            }
        }
        
        // Return unchanged elements if no changes were made or if there was an error
        return [];
    }
}