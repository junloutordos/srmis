<?php

namespace Tests\Unit\Support;

use App\Support\TenantChannel;
use PHPUnit\Framework\TestCase;

class TenantChannelTest extends TestCase
{
    public function test_same_user_id_in_different_tenants_gets_different_channels(): void
    {
        $this->assertNotSame(TenantChannel::user(5, 'crc'), TenantChannel::user(5, 'oed'));
        $this->assertSame('tenant.crc.user.5', TenantChannel::user(5, 'crc'));
    }

    public function test_same_conversation_id_in_different_tenants_gets_different_channels(): void
    {
        $this->assertNotSame(TenantChannel::conversation(3, 'crc'), TenantChannel::conversation(3, 'oed'));
        $this->assertSame('tenant.oed.conversation.3', TenantChannel::conversation(3, 'oed'));
    }
}
