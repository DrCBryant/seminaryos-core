<?php

namespace App\Filament\Resources\OfficialTranscripts\Tables;

use App\Filament\Resources\OfficialTranscripts\Schemas\OfficialTranscriptForm;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class OfficialTranscriptsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('student.full_name')
                    ->label('Student')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),
                TextColumn::make('transcript_number')
                    ->label('Transcript number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => OfficialTranscriptForm::STATUS_OPTIONS[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('purpose')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('requested_at')
                    ->label('Requested date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('issued_at')
                    ->label('Issued date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('delivery_method')
                    ->label('Delivery method')
                    ->formatStateUsing(fn (?string $state): string => $state ? (OfficialTranscriptForm::DELIVERY_METHOD_OPTIONS[$state] ?? $state) : '—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(OfficialTranscriptForm::STATUS_OPTIONS),
                SelectFilter::make('delivery_method')
                    ->options(OfficialTranscriptForm::DELIVERY_METHOD_OPTIONS),
                Filter::make('requested_at')
                    ->label('Requested date')
                    ->query(fn ($query) => $query->whereNotNull('requested_at')),
                Filter::make('issued_at')
                    ->label('Issued date')
                    ->query(fn ($query) => $query->whereNotNull('issued_at')),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
