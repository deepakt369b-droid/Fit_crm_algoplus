<?php

namespace App\Filament\Resources\WhatsappConversations\Pages;

use App\Filament\Resources\WhatsappConversations\Schemas\WhatsappConversationInfolist;
use App\Filament\Resources\WhatsappConversations\WhatsappConversationResource;
use App\Models\WhatsappConversation;
use App\Models\WhatsappTemplate;
use App\Services\WhatsApp\OutboundMessageSender;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use RuntimeException;

class ViewWhatsappConversation extends ViewRecord
{
    protected static string $resource = WhatsappConversationResource::class;

    public function infolist(Schema $schema): Schema
    {
        return WhatsappConversationInfolist::configure($schema);
    }

    public function getBreadcrumbs(): array
    {
        return [
            __('app.navigation.groups.marketing'),
            WhatsappConversationResource::getNavigationLabel(),
        ];
    }

    protected function getHeaderActions(): array
    {
        /** @var WhatsappConversation $conversation */
        $conversation = $this->getRecord();
        $withinWindow = $conversation->contact->isWithinServiceWindow();

        return [
            Action::make('reply')
                ->label(__('app.whatsapp.reply'))
                ->icon('heroicon-o-paper-airplane')
                ->modalWidth('lg')
                ->form([
                    Textarea::make('body')
                        ->label(__('app.whatsapp.message_body'))
                        ->required()
                        ->rows(4)
                        ->visible($withinWindow),
                    Select::make('template_id')
                        ->label(__('app.whatsapp.select_template'))
                        ->options(fn () => WhatsappTemplate::query()
                            ->where('wa_phone_number_id', $conversation->wa_phone_number_id)
                            ->where('status', 'approved')
                            ->pluck('name', 'id'))
                        ->required()
                        ->helperText(__('app.whatsapp.outside_window_hint'))
                        ->visible(! $withinWindow),
                ])
                ->action(function (array $data) use ($conversation, $withinWindow): void {
                    try {
                        if ($withinWindow) {
                            app(OutboundMessageSender::class)->sendText(
                                $conversation,
                                (string) $data['body'],
                                auth()->id(),
                            );
                        } else {
                            $template = WhatsappTemplate::query()->findOrFail($data['template_id']);
                            app(OutboundMessageSender::class)->sendTemplate(
                                $conversation,
                                $template,
                                sentByUserId: auth()->id(),
                            );
                        }

                        Notification::make()
                            ->title(__('app.whatsapp.message_sent'))
                            ->success()
                            ->send();
                    } catch (RuntimeException $exception) {
                        Notification::make()
                            ->title(__('app.whatsapp.message_send_failed'))
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('markResolved')
                ->label(__('app.whatsapp.mark_read'))
                ->icon('heroicon-o-check')
                ->visible(fn (): bool => $conversation->unread_count > 0)
                ->action(function () use ($conversation): void {
                    $conversation->forceFill(['unread_count' => 0])->save();
                }),
        ];
    }
}
