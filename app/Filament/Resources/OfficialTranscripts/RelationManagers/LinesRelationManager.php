<?php

namespace App\Filament\Resources\OfficialTranscripts\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Transcript Lines';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('course_title')
            ->columns([
                TextColumn::make('term_label')
                    ->label('Term')
                    ->toggleable(),
                TextColumn::make('course_code')
                    ->label('Course code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('course_title')
                    ->label('Course title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('credits_attempted')
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('credits_earned')
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('final_grade')
                    ->label('Final grade'),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('completed_at')
                    ->date(),
                TextColumn::make('sort_order')
                    ->label('Sort')
                    ->sortable(),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
