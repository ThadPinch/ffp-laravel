<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Fields\BelongsTo;


class PdfJob extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\PdfJob>
     */
    public static $model = \App\Models\PdfJob::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'id';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @return array<int, \Laravel\Nova\Fields\Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),
            
            BelongsTo::make('User')
                ->sortable(),
                
            BelongsTo::make('Design')
                ->sortable(),
                
            Text::make('Job ID')
                ->sortable()
                ->rules('required', 'uuid')
                ->hideWhenCreating(),
                
            Select::make('Type')
                ->options([
                    'standard' => 'Standard',
                    'print_ready' => 'Print Ready',
                ])
                ->default('standard')
                ->sortable(),
                
            Select::make('Status')
                ->options([
                    'pending' => 'Pending',
                    'processing' => 'Processing',
                    'completed' => 'Completed',
                    'failed' => 'Failed',
                ])
                ->sortable(),
                
            Text::make('File Path')
                ->hideFromIndex(),
                
            Textarea::make('Error Message')
                ->hideFromIndex()
                ->alwaysShow(),
                
            DateTime::make('Created At')
                ->sortable()
                ->hideWhenCreating()
                ->hideWhenUpdating(),
                
            DateTime::make('Updated At')
                ->sortable()
                ->hideWhenCreating()
                ->hideWhenUpdating(),
        ];
    }

    /**
     * Get the cards available for the resource.
     *
     * @return array<int, \Laravel\Nova\Card>
     */
    public function cards(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @return array<int, \Laravel\Nova\Filters\Filter>
     */
    public function filters(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @return array<int, \Laravel\Nova\Lenses\Lens>
     */
    public function lenses(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @return array<int, \Laravel\Nova\Actions\Action>
     */
    public function actions(NovaRequest $request): array
    {
        return [];
    }
}
