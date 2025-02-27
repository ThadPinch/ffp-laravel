<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DesignAIController extends Controller
{
    public function edit(Request $request)
    {
        $request->validate([
            'prompt' => 'required|string',
            'currentElements' => 'required|array',
            'productInfo' => 'required|array'
        ]);

        try {
            // In a real application, you would connect to an AI service like Anthropic
            // For this example, we'll simulate some basic responses
            
            $prompt = strtolower($request->prompt);
            $elements = $request->currentElements;
            
            // Process the prompt to determine what changes to make
            $response = [
                'message' => 'I\'ve updated your design.',
                'elements' => $elements // Default to no changes
            ];
            
            // Basic pattern matching for different types of requests
            if (str_contains($prompt, 'add text') || str_contains($prompt, 'add a text')) {
                // Add a new text element
                $position = $this->extractPosition($prompt);
                $content = $this->extractContent($prompt, 'text');
                
                $newElement = [
                    'id' => time(),
                    'type' => 'text',
                    'x' => $position['x'],
                    'y' => $position['y'],
                    'width' => 300,
                    'height' => 100,
                    'content' => $content ?: 'New Text',
                    'rotation' => 0,
                    'fontSize' => 48,
                    'fontFamily' => 'Arial',
                    'color' => '#000000',
                    'zIndex' => count($elements)
                ];
                
                $response['elements'][] = $newElement;
                $response['message'] = "I've added a new text element that says '{$newElement['content']}'.";
            }
            elseif (str_contains($prompt, 'add shape') || str_contains($prompt, 'add a shape')) {
                // Add a new shape element
                $position = $this->extractPosition($prompt);
                
                $newElement = [
                    'id' => time(),
                    'type' => 'shape',
                    'x' => $position['x'],
                    'y' => $position['y'],
                    'width' => 300,
                    'height' => 300,
                    'rotation' => 0,
                    'color' => $this->extractColor($prompt) ?: '#FFFFFF',
                    'borderColor' => '#000000',
                    'borderRadius' => str_contains($prompt, 'circle') ? 150 : 0,
                    'zIndex' => count($elements)
                ];
                
                $response['elements'][] = $newElement;
                $response['message'] = "I've added a new shape to your design.";
            }
            elseif (preg_match('/change (?:the )?color of (?:the )?(.+?) to (.+)/i', $prompt, $matches)) {
                // Change color of an element
                $elementDesc = $matches[1];
                $colorDesc = $matches[2];
                $color = $this->colorNameToHex($colorDesc);
                
                // Find matching elements and update their color
                $changed = false;
                foreach ($response['elements'] as &$element) {
                    if ($element['type'] === 'text' && 
                        (str_contains(strtolower($element['content']), strtolower($elementDesc)) || 
                         $elementDesc === 'text')) {
                        $element['color'] = $color;
                        $changed = true;
                    }
                    elseif ($element['type'] === 'shape' && $elementDesc === 'shape') {
                        $element['color'] = $color;
                        $changed = true;
                    }
                }
                
                if ($changed) {
                    $response['message'] = "I've changed the color to {$colorDesc}.";
                } else {
                    $response['message'] = "I couldn't find any elements matching '{$elementDesc}' to change the color.";
                }
            }
            elseif (str_contains($prompt, 'remove') || str_contains($prompt, 'delete')) {
                // Remove an element
                $elementDesc = null;
                
                if (str_contains($prompt, 'text')) {
                    $elementDesc = 'text';
                } elseif (str_contains($prompt, 'shape')) {
                    $elementDesc = 'shape';
                } elseif (str_contains($prompt, 'image')) {
                    $elementDesc = 'image';
                }
                
                if ($elementDesc) {
                    $updatedElements = [];
                    $removed = false;
                    
                    foreach ($elements as $element) {
                        if ($element['type'] === $elementDesc && !$removed) {
                            $removed = true;
                            continue;
                        }
                        $updatedElements[] = $element;
                    }
                    
                    if ($removed) {
                        $response['elements'] = $updatedElements;
                        $response['message'] = "I've removed a {$elementDesc} element from your design.";
                    } else {
                        $response['message'] = "I couldn't find any {$elementDesc} elements to remove.";
                    }
                } else {
                    $response['message'] = "Please specify what type of element you'd like to remove (text, shape, or image).";
                }
            }
            elseif (str_contains($prompt, 'resize') || str_contains($prompt, 'larger') || str_contains($prompt, 'smaller')) {
                // Resize an element
                $elementType = null;
                $scale = 1.5; // Default scale factor
                
                if (str_contains($prompt, 'smaller')) {
                    $scale = 0.75; // Reduce size
                }
                
                if (str_contains($prompt, 'text')) {
                    $elementType = 'text';
                } elseif (str_contains($prompt, 'shape')) {
                    $elementType = 'shape';
                } elseif (str_contains($prompt, 'image')) {
                    $elementType = 'image';
                }
                
                if ($elementType) {
                    $modified = false;
                    
                    foreach ($response['elements'] as &$element) {
                        if ($element['type'] === $elementType && !$modified) {
                            $element['width'] *= $scale;
                            $element['height'] *= $scale;
                            
                            if ($elementType === 'text') {
                                $element['fontSize'] *= $scale;
                            }
                            
                            $modified = true;
                        }
                    }
                    
                    if ($modified) {
                        $action = $scale > 1 ? 'increased' : 'decreased';
                        $response['message'] = "I've {$action} the size of a {$elementType} element.";
                    } else {
                        $response['message'] = "I couldn't find any {$elementType} elements to resize.";
                    }
                } else {
                    $response['message'] = "Please specify what you'd like to resize (text, shape, or image).";
                }
            }
            else {
                // No specific action recognized
                $response['message'] = "I'm not sure how to help with that. You can ask me to add text or shapes, change colors, remove elements, or resize things.";
            }
            
            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('Design AI error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Sorry, I encountered an error while processing your request. Please try again.'
            ], 500);
        }
    }
    
    // Helper methods to extract information from prompts
    
    private function extractPosition($prompt)
    {
        $x = 200;
        $y = 200;
        
        if (str_contains($prompt, 'top')) {
            $y = 100;
        } elseif (str_contains($prompt, 'bottom')) {
            $y = 500;
        }
        
        if (str_contains($prompt, 'left')) {
            $x = 100;
        } elseif (str_contains($prompt, 'right')) {
            $x = 500;
        } elseif (str_contains($prompt, 'center')) {
            $x = 300;
        }
        
        return ['x' => $x, 'y' => $y];
    }
    
    private function extractContent($prompt, $type)
    {
        if ($type === 'text') {
            if (preg_match('/(?:add|create|insert) (?:a |an )?(?:text|title|heading) (?:that |which )?says ["\']?([^"\']+)["\']?/i', $prompt, $matches)) {
                return $matches[1];
            }
            
            if (preg_match('/(?:add|create|insert) ["\']([^"\']+)["\'](?:.+?)?(?:text|title|heading)/i', $prompt, $matches)) {
                return $matches[1];
            }
            
            if (preg_match('/(?:add|create|insert) (?:a |an )?(?:text|title|heading) ["\']([^"\']+)["\']?/i', $prompt, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }
    
    private function extractColor($prompt)
    {
        $colorMap = [
            'red' => '#FF0000',
            'green' => '#00FF00',
            'blue' => '#0000FF',
            'yellow' => '#FFFF00',
            'orange' => '#FFA500',
            'purple' => '#800080',
            'pink' => '#FFC0CB',
            'black' => '#000000',
            'white' => '#FFFFFF',
            'gray' => '#808080',
            'grey' => '#808080',
            'brown' => '#A52A2A',
            'cyan' => '#00FFFF',
            'magenta' => '#FF00FF',
            'teal' => '#008080'
        ];
        
        foreach ($colorMap as $name => $hex) {
            if (str_contains($prompt, $name)) {
                return $hex;
            }
        }
        
        if (preg_match('/#([0-9A-F]{6})/i', $prompt, $matches)) {
            return '#' . $matches[1];
        }
        
        return null;
    }
    
    private function colorNameToHex($name)
    {
        $colorMap = [
            'red' => '#FF0000',
            'green' => '#00FF00',
            'blue' => '#0000FF',
            'yellow' => '#FFFF00',
            'orange' => '#FFA500',
            'purple' => '#800080',
            'pink' => '#FFC0CB',
            'black' => '#000000',
            'white' => '#FFFFFF',
            'gray' => '#808080',
            'grey' => '#808080',
            'brown' => '#A52A2A',
            'cyan' => '#00FFFF',
            'magenta' => '#FF00FF',
            'teal' => '#008080'
        ];
        
        $name = strtolower(trim($name));
        
        if (isset($colorMap[$name])) {
            return $colorMap[$name];
        }
        
        if (preg_match('/#([0-9A-F]{6})/i', $name, $matches)) {
            return '#' . $matches[1];
        }
        
        return '#000000'; // Default to black if color not recognized
    }
}

