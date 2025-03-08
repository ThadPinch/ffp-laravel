<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $designName }} (Print Ready)</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            width: {{ $pdfWidth }}in;
            height: {{ $pdfHeight }}in;
            position: relative;
        }
        .design-container {
            position: absolute;
            top: {{ $cropMarkOffset }}in;
            left: {{ $cropMarkOffset }}in;
            width: {{ $width }}in;
            height: {{ $height }}in;
            overflow: hidden;
        }
        .design-element {
            position: absolute;
            transform-origin: center center;
        }
        .text-element {
            overflow: hidden;
            word-wrap: break-word;
        }
        
        /* Crop marks */
        .crop-mark {
            position: absolute;
            background-color: #000;
            pointer-events: none;
        }
        .crop-mark-h {
            height: 0.5pt;
            width: {{ $cropMarkLength }}in;
        }
        .crop-mark-v {
            width: 0.5pt;
            height: {{ $cropMarkLength }}in;
        }
        
        /* Print info */
        .print-info {
            position: absolute;
            bottom: 0.1in;
            left: 0.1in;
            font-size: 6pt;
            font-family: Arial, sans-serif;
            color: #999;
        }
        
        /* Trim line and bleed indicators */
        .trim-box {
            position: absolute;
            border: 0.25pt solid #00F;
            top: {{ $cropMarkOffset + $bleed }}in;
            left: {{ $cropMarkOffset + $bleed }}in;
            width: {{ $product->finished_width }}in;
            height: {{ $product->finished_length }}in;
            box-sizing: border-box;
            pointer-events: none;
        }
        .bleed-box {
            position: absolute;
            border: 0.25pt dashed #F00;
            top: {{ $cropMarkOffset }}in;
            left: {{ $cropMarkOffset }}in;
            width: {{ $width }}in;
            height: {{ $height }}in;
            box-sizing: border-box;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <!-- Design container with all elements -->
    <div class="design-container">
        @foreach($elements as $element)
            @php
                // Convert dimensions from pixels to inches (assuming 72 dpi)
                $x = $element['x'] / 72;
                $y = $element['y'] / 72;
                $width = $element['width'] / 72;
                $height = $element['height'] / 72;
                $rotation = $element['rotation'] ?? 0;
                
                // Calculate style
                $style = "left: {$x}in; top: {$y}in; width: {$width}in; height: {$height}in;";
                if ($rotation) {
                    $style .= "transform: rotate({$rotation}deg);";
                }
            @endphp
            
            <div class="design-element" style="{{ $style }}">
                @if($element['type'] == 'text')
                    <div class="text-element" style="
                        font-family: {{ $element['fontFamily'] ?? 'Arial' }}; 
                        font-size: {{ ($element['fontSize'] / 72) }}in;
                        color: {{ $element['color'] ?? '#000000' }};
                        text-align: {{ $element['textAlign'] ?? 'left' }};
                    ">
                        {!! nl2br(e($element['content'])) !!}
                    </div>
                @elseif($element['type'] == 'shape')
                    <div style="
                        width: 100%; 
                        height: 100%; 
                        background-color: {{ $element['color'] ?? '#FFFFFF' }}; 
                        border: {{ isset($element['borderColor']) ? '2px solid '.$element['borderColor'] : 'none' }};
                        border-radius: {{ isset($element['borderRadius']) ? ($element['borderRadius']/72).'in' : '0' }};
                    "></div>
                @elseif($element['type'] == 'image' && isset($element['src']))
                    <img src="{{ $element['src'] }}" style="width: 100%; height: 100%; object-fit: contain;">
                @endif
            </div>
        @endforeach
    </div>
    
    <!-- Crop marks -->
    <!-- Top-left -->
    <div class="crop-mark crop-mark-h" style="top: {{ $cropMarkOffset }}in; left: 0;"></div>
    <div class="crop-mark crop-mark-v" style="top: 0; left: {{ $cropMarkOffset }}in;"></div>
    
    <!-- Top-right -->
    <div class="crop-mark crop-mark-h" style="top: {{ $cropMarkOffset }}in; right: 0;"></div>
    <div class="crop-mark crop-mark-v" style="top: 0; right: {{ $cropMarkOffset + $width }}in;"></div>
    
    <!-- Bottom-left -->
    <div class="crop-mark crop-mark-h" style="bottom: {{ $pdfHeight - $cropMarkOffset - $height }}in; left: 0;"></div>
    <div class="crop-mark crop-mark-v" style="bottom: 0; left: {{ $cropMarkOffset }}in;"></div>
    
    <!-- Bottom-right -->
    <div class="crop-mark crop-mark-h" style="bottom: {{ $pdfHeight - $cropMarkOffset - $height }}in; right: 0;"></div>
    <div class="crop-mark crop-mark-v" style="bottom: 0; right: {{ $cropMarkOffset + $width }}in;"></div>
    
    <!-- Trim and bleed boxes -->
    <div class="trim-box"></div>
    <div class="bleed-box"></div>
    
    <!-- Print information -->
    <div class="print-info">
        {{ $designName }} | {{ $product->name }} | {{ date('Y-m-d H:i') }}
        @if(isset($metadata['order_id'])) | Order: {{ $metadata['order_id'] }} @endif
        @if(isset($metadata['customer_name'])) | Customer: {{ $metadata['customer_name'] }} @endif
        | Size: {{ $product->finished_width }}" × {{ $product->finished_length }}" | Bleed: {{ $bleed }}"
    </div>
</body>
</html>