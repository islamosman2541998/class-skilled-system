<?php

namespace App\Enums;

enum FollowUpType: string
{
    case Call = 'call';
    case Message = 'message';
    case Note = 'note';

    public function label(): string
    {
        return __('leads.follow_up_types.'.$this->value);
    }

    public function icon(): string
    {
        return match ($this) {
            self::Call => 'phone',
            self::Message => 'message',
            self::Note => 'note',
        };
    }
}
