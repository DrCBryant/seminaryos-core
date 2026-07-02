<?php

namespace App\Filament\Resources\Applicants\Schemas;

use App\Models\Program;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ApplicantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Applicant')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('first_name')
                                    ->label('First Name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('last_name')
                                    ->label('Last Name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('email')
                                    ->email()
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('phone')
                                    ->tel()
                                    ->maxLength(50),
                                TextInput::make('source')
                                    ->maxLength(255),
                                Select::make('program_id')
                                    ->label('Program Applied For')
                                    ->relationship('program', 'title', fn ($query) => $query->orderBy('title'))
                                    ->getOptionLabelFromRecordUsing(fn (Program $record): string => "{$record->code} - {$record->title}")
                                    ->searchable(['code', 'title'])
                                    ->preload()
                                    ->required(),
                                Select::make('status')
                                    ->options([
                                        'inquiry' => 'Inquiry',
                                        'applied' => 'Applied',
                                        'under_review' => 'Under Review',
                                        'accepted' => 'Accepted',
                                        'denied' => 'Denied',
                                        'enrolled' => 'Enrolled',
                                    ])
                                    ->required(),
                                DateTimePicker::make('submitted_at')
                                    ->seconds(false),
                            ]),
                        Textarea::make('notes')
                            ->rows(5)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
