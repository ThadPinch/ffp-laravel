<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $designName }}</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            width: {{ $width }}in;
            height: {{ $height }}in;
            position: relative;
        }
        .design-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        .design-element {
            position: absolute;
            transform-origin: center center;
        }
        .text-element {
            overflow: hidden;
            word-wrap: break-word;
        }
        .trim-line {
            position: absolute;
            border: 1px dashed #ff0000;
            top: {{ $product->bleed }}in;
            left: {{ $product->bleed }}in;
            width: {{ $product->finished_width }}in;
            height: {{ $product->finished_length }}in;
            box-sizing: border-box;
            pointer-events: none;
        }
    </style>
</head>
<body>
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
</body>
</html>