<?php

namespace App\Nova;

use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Image;
use Laravel\Nova\Http\Requests\NovaRequest;

class Product extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Product>
     */
    public static $model = \App\Models\Product::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'name';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id', 'name', 'production_product_id',
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            ID::make()->sortable(),

            Text::make('Name')
                ->sortable()
                ->rules('required', 'max:255'),

            Number::make('Finished Width')
                ->step(0.01)
                ->rules('required', 'numeric', 'min:0')
                ->help('Width in inches')
                ->sortable(),

            Number::make('Finished Length')
                ->step(0.01)
                ->rules('required', 'numeric', 'min:0')
                ->help('Length in inches')
                ->sortable(),

            Number::make('Per Piece Weight')
                ->step(0.01)
                ->rules('nullable', 'numeric', 'min:0')
                ->help('Weight in ounces/grams')
                ->nullable()
                ->sortable(),

            Textarea::make('Description')
                ->nullable()
                ->alwaysShow()
                ->rules('nullable', 'string'),

            Text::make('Production Product ID')
                ->nullable()
                ->help('Integration ID for production system')
                ->rules('nullable', 'string', 'max:255'),

            Image::make('Product Image')
                ->disk('public')
                ->path('products')
                ->prunable()
                ->nullable()
                ->help('Upload product image'),

            Number::make('Bleed')
                ->step(0.01)
                ->rules('nullable', 'numeric', 'min:0')
                ->help('Bleed in inches')
                ->nullable()
                ->sortable(),
        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function cards(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function filters(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function lenses(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        return [];
    }
}