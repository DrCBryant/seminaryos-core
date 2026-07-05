<?php

namespace App\Filament\Resources\CourseOfferings\RelationManagers;

use App\Models\SectionAssignment;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SectionAssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'sectionAssignments';

    protected static ?string $title = 'Section Assignments';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('sort_order')
            ->modifyQueryUsing(fn ($query) => $query->orderBy('sort_order')->orderBy('due_at')->orderBy('id'))
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('assignment_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => SectionAssignment::ASSIGNMENT_TYPE_OPTIONS[$state] ?? $state)
                    ->sortable(),
                IconColumn::make('is_required')
                    ->label('Required')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('due_at')
                    ->label('Due date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => SectionAssignment::STATUS_OPTIONS[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->label('Sort order')
                    ->numeric()
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $ownerRecord = $this->getOwnerRecord();

                        $data['institution_id'] = $ownerRecord->institution_id;
                        $data['course_offering_id'] = $ownerRecord->id;

                        return $data;
                    })
                    ->form($this->assignmentFormSchema()),
            ])
            ->recordActions([
                EditAction::make()
                    ->form($this->assignmentFormSchema()),
                Action::make('archive')
                    ->icon('heroicon-o-archive-box')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (SectionAssignment $record): bool => $record->status !== SectionAssignment::STATUS_ARCHIVED)
                    ->action(fn (SectionAssignment $record): bool => $record->update([
                        'status' => SectionAssignment::STATUS_ARCHIVED,
                    ])),
            ]);
    }

    /**
     * @return array<int, mixed>
     */
    protected function assignmentFormSchema(): array
    {
        return [
            Hidden::make('institution_id')
                ->default(fn (): ?int => $this->getOwnerRecord()->institution_id),
            Hidden::make('course_offering_id')
                ->default(fn (): ?int => $this->getOwnerRecord()->id),
            TextInput::make('title')
                ->required()
                ->maxLength(255),
            Select::make('assignment_type')
                ->label('Assignment type')
                ->options(SectionAssignment::ASSIGNMENT_TYPE_OPTIONS)
                ->default(SectionAssignment::TYPE_ASSIGNMENT)
                ->required(),
            Select::make('requirement_basis')
                ->label('Requirement basis')
                ->options(SectionAssignment::REQUIREMENT_BASIS_OPTIONS),
            TextInput::make('points_possible')
                ->numeric()
                ->step('0.01'),
            TextInput::make('passing_threshold')
                ->label('Passing threshold')
                ->maxLength(255),
            DateTimePicker::make('available_from'),
            DateTimePicker::make('due_at'),
            DateTimePicker::make('available_until'),
            Toggle::make('is_required')
                ->label('Required')
                ->default(true),
            TextInput::make('sort_order')
                ->numeric()
                ->default(0)
                ->required(),
            Select::make('status')
                ->options(SectionAssignment::STATUS_OPTIONS)
                ->default(SectionAssignment::STATUS_DRAFT)
                ->required(),
            Textarea::make('description')
                ->rows(4)
                ->columnSpanFull(),
            Textarea::make('instructions')
                ->rows(5)
                ->columnSpanFull(),
            Textarea::make('notes')
                ->rows(4)
                ->columnSpanFull(),
        ];
    }
}
