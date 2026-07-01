<?php

namespace App\Filament\Resources\GradeScales\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GradeValuesRelationManager extends RelationManager
{
    protected static string $relationship = 'gradeValues';

    protected static ?string $title = 'Grade Values';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('grade')
            ->columns([
                TextColumn::make('grade')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('label')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('grade_points')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                IconColumn::make('earns_credit')
                    ->boolean(),
                IconColumn::make('affects_gpa')
                    ->boolean(),
                IconColumn::make('is_passing')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->form([
                        Hidden::make('institution_id')
                            ->default(fn () => $this->getOwnerRecord()->institution_id),
                        TextInput::make('grade')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('label')
                            ->maxLength(255),
                        TextInput::make('grade_points')
                            ->numeric(),
                        TextInput::make('min_percentage')
                            ->numeric(),
                        TextInput::make('max_percentage')
                            ->numeric(),
                        Toggle::make('earns_credit')
                            ->default(true),
                        Toggle::make('affects_gpa')
                            ->default(true),
                        Toggle::make('is_passing')
                            ->default(true),
                        TextInput::make('sort_order')
                            ->numeric(),
                        Textarea::make('notes')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->form([
                        TextInput::make('grade')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('label')
                            ->maxLength(255),
                        TextInput::make('grade_points')
                            ->numeric(),
                        TextInput::make('min_percentage')
                            ->numeric(),
                        TextInput::make('max_percentage')
                            ->numeric(),
                        Toggle::make('earns_credit'),
                        Toggle::make('affects_gpa'),
                        Toggle::make('is_passing'),
                        TextInput::make('sort_order')
                            ->numeric(),
                        Textarea::make('notes')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
