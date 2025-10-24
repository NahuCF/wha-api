<?php

namespace App\Enums;

enum PlanType: string
{
    case GROWTH = 'growth';
    case SCALE = 'scale';
    case PRO = 'pro';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::GROWTH => 'Growth',
            self::SCALE => 'Scale',
            self::PRO => 'Pro',
        };
    }

    public function monthlyPrice(): int
    {
        return match ($this) {
            self::GROWTH => 39,
            self::SCALE => 79,
            self::PRO => 199,
        };
    }

    public function yearlyPrice(): int
    {
        return match ($this) {
            self::GROWTH => 29,
            self::SCALE => 59,
            self::PRO => 149,
        };
    }

    public function maxUsers(): ?int
    {
        return match ($this) {
            self::GROWTH => 3,
            self::SCALE => 5,
            self::PRO => null,
        };
    }

    public function maxContacts(): ?int
    {
        return match ($this) {
            self::GROWTH => 5000,
            self::SCALE => 10000,
            self::PRO => null,
        };
    }

    public function maxWhatsappNumbers(): ?int
    {
        return match ($this) {
            self::GROWTH => 1,
            self::SCALE => 2,
            self::PRO => null,
        };
    }

    public function features(): array
    {
        return match ($this) {
            self::GROWTH => [
                'basic_chatbot',
                'message_analytics',
                'multi_agent_chat',
                'bulk_messaging',
            ],
            self::SCALE => [
                'basic_chatbot',
                'advanced_chatbot',
                'message_analytics',
                'user_analytics',
                'multi_agent_chat',
                'bulk_messaging',
                'roles_permissions',
            ],
            self::PRO => [
                'basic_chatbot',
                'advanced_chatbot',
                'message_analytics',
                'user_analytics',
                'multi_agent_chat',
                'bulk_messaging',
                'roles_permissions',
                'custom_integrations',
            ],
        };
    }

    public function allowsExtraUsers(): bool
    {
        return match ($this) {
            self::GROWTH => false,
            self::SCALE, self::PRO => true,
        };
    }

    public function extraUserPrice(): int
    {
        return match ($this) {
            self::GROWTH => 0,
            self::SCALE => 15,
            self::PRO => 35,
        };
    }
}
