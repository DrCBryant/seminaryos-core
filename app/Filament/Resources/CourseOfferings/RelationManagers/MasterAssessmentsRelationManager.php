<?php

namespace App\Filament\Resources\CourseOfferings\RelationManagers;

use App\Filament\Resources\MasterAssessments\MasterAssessmentResource;
use App\Models\MasterAssessment;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MasterAssessmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'masterAssessments';

    protected static ?string $title = 'Master Assessments';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('passing_threshold')
                    ->label('Passing threshold'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => MasterAssessment::STATUS_OPTIONS[$state] ?? $state)
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
                    ->form([
                        Hidden::make('institution_id')->default(fn (): ?int => $this->getOwnerRecord()->institution_id),
                        Hidden::make('course_offering_id')->default(fn (): ?int => $this->getOwnerRecord()->id),
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('passing_threshold')
                            ->label('Passing threshold')
                            ->maxLength(255),
                        Select::make('status')
                            ->options(MasterAssessment::STATUS_OPTIONS)
                            ->default(MasterAssessment::STATUS_DRAFT)
                            ->required(),
                        Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull(),
                        Textarea::make('competency_outcomes')
                            ->label('Competency outcomes')
                            ->rows(5)
                            ->columnSpanFull(),
                        Textarea::make('rubric')
                            ->rows(5)
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->url(fn (MasterAssessment $record): string => MasterAssessmentResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
