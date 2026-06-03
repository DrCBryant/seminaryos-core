<?php

namespace App\Filament\Resources\Courses\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProgramsRelationManager extends RelationManager
{
    protected static string $relationship = 'programs';

    protected static ?string $title = 'Programs';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('pivot.requirement_type')
                    ->label('Requirement type'),
                TextColumn::make('pivot.sequence_order')
                    ->label('Sequence')
                    ->sortable(),
                TextColumn::make('pivot.credits_applied')
                    ->label('Credits applied')
                    ->numeric(decimalPlaces: 2),
            ])
            ->headerActions([
                AttachAction::make()
                    ->recordSelectSearchColumns(['code', 'title'])
                    ->form(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Hidden::make('institution_id')
                            ->default(fn () => $this->getOwnerRecord()->institution_id),
                        Select::make('requirement_type')
                            ->options([
                                'required' => 'Required',
                                'elective' => 'Elective',
                                'recommended' => 'Recommended',
                            ]),
                        TextInput::make('sequence_order')
                            ->numeric(),
                        TextInput::make('credits_applied')
                            ->numeric(),
                    ]),
            ])
            ->recordActions([
                DetachAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}
