<?php

namespace App\Filament\Resources\WhatsappBroadcasts\Tables;

use App\Models\WhatsappBroadcast;
use App\Models\WhatsappContact;
use App\Services\WhatsApp\BroadcastDispatcher;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WhatsappBroadcastTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->poll('15s')
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.fields.name'))
                    ->searchable(),
                TextColumn::make('phoneNumber.display_phone_number')
                    ->label(__('app.resources.whatsapp_phone_numbers.singular')),
                TextColumn::make('template.name')
                    ->label(__('app.resources.whatsapp_templates.singular')),
                TextColumn::make('status')
                    ->label(__('app.fields.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'failed', 'throttled' => 'danger',
                        'sending', 'scheduled' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('total_recipients')
                    ->label(__('app.whatsapp.recipients')),
                TextColumn::make('sent_count')
                    ->label(__('app.whatsapp.sent')),
                TextColumn::make('delivered_count')
                    ->label(__('app.whatsapp.delivered')),
                TextColumn::make('failed_count')
                    ->label(__('app.whatsapp.failed')),
            ])
            ->defaultSort('id', 'desc')
            ->emptyStateIcon('heroicon-o-megaphone')
            ->emptyStateHeading(__('app.empty.no_records', ['records' => __('app.resources.whatsapp_broadcasts.plural')]))
            ->emptyStateActions([
                CreateAction::make()
                    ->label(__('app.actions.new', ['resource' => __('app.resources.whatsapp_broadcasts.singular')]))
                    ->modalWidth('lg')
                    ->createAnother(false),
            ])
            ->recordActions([
                Action::make('send')
                    ->label(__('app.whatsapp.send_broadcast'))
                    ->icon('heroicon-o-paper-airplane')
                    ->visible(fn (WhatsappBroadcast $record): bool => $record->status === 'draft')
                    ->modalWidth('lg')
                    ->form([
                        Radio::make('audience')
                            ->label(__('app.whatsapp.audience'))
                            ->options([
                                'all_opted_in' => __('app.whatsapp.audience_all_opted_in'),
                                'specific' => __('app.whatsapp.audience_specific'),
                            ])
                            ->default('all_opted_in')
                            ->live()
                            ->required(),
                        Select::make('contact_ids')
                            ->label(__('app.resources.whatsapp_contacts.plural'))
                            ->multiple()
                            ->searchable()
                            ->options(fn (): array => WhatsappContact::query()
                                ->where('opt_in_status', 'opted_in')
                                ->pluck('name', 'id')
                                ->all())
                            ->required()
                            ->visible(fn (callable $get): bool => $get('audience') === 'specific'),
                    ])
                    ->action(function (WhatsappBroadcast $record, array $data): void {
                        $contacts = $data['audience'] === 'specific'
                            ? WhatsappContact::query()->whereIn('id', $data['contact_ids'])->get()
                            : app(BroadcastDispatcher::class)->optedInContactsQuery($record)->get();

                        if ($contacts->isEmpty()) {
                            Notification::make()
                                ->title(__('app.whatsapp.no_eligible_recipients'))
                                ->warning()
                                ->send();

                            return;
                        }

                        // v1: personalizes only the contact's own name into
                        // the template's first placeholder ({{1}}) — a
                        // simplified stand-in for full per-recipient
                        // variable mapping, which would need a UI driven by
                        // the template's actual component/placeholder count.
                        $variablesByContactId = $contacts
                            ->mapWithKeys(fn (WhatsappContact $contact): array => [
                                $contact->id => [$contact->name ?? ''],
                            ])
                            ->all();

                        app(BroadcastDispatcher::class)->dispatch($record, $contacts, $variablesByContactId);

                        Notification::make()
                            ->title(__('app.whatsapp.broadcast_started', ['count' => $contacts->count()]))
                            ->success()
                            ->send();
                    }),
                ViewAction::make(),
                DeleteAction::make()
                    ->visible(fn (WhatsappBroadcast $record): bool => $record->status === 'draft'),
            ]);
    }
}
