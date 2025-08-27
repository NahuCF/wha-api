<?php

namespace App\Enums;

enum MessageType: string
{
    case TEXT = 'text';
    case IMAGE = 'image';
    case VIDEO = 'video';
    case AUDIO = 'audio';
    case DOCUMENT = 'document';
    case LOCATION = 'location';
    case CONTACTS = 'contacts';
    case STICKER = 'sticker';
    case TEMPLATE = 'template';
    case INTERACTIVE = 'interactive';
    case BUTTON = 'button';
    case REACTION = 'reaction';
    case ORDER = 'order';
    case SYSTEM = 'system';
    case INVOICE = 'invoice';

    public function isMedia(): bool
    {
        return in_array($this, [
            self::IMAGE,
            self::VIDEO,
            self::AUDIO,
            self::DOCUMENT,
            self::STICKER,
        ]);
    }

    public function isInteractive(): bool
    {
        return in_array($this, [
            self::INTERACTIVE,
            self::BUTTON,
        ]);
    }

    public function requiresMedia(): bool
    {
        return $this->isMedia();
    }

    public function requiresTemplateData(): bool
    {
        return $this === self::TEMPLATE;
    }

    public function requiresLocationData(): bool
    {
        return $this === self::LOCATION;
    }

    public function requiresContactsData(): bool
    {
        return $this === self::CONTACTS;
    }

    public function requiresInteractiveData(): bool
    {
        return $this->isInteractive();
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
