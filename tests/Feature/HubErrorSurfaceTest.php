<?php

declare(strict_types=1);

use App\Contracts\HubClient;
use App\Exceptions\HubUnreachableException;
use App\Livewire\Alerts\AlertList;
use App\Livewire\Apps\AppList;
use App\Livewire\Dashboard;
use App\Livewire\Infra\TargetDetail;
use App\Livewire\Infra\TargetList;
use App\Livewire\Logs\LogSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

/**
 * Verifies all six data screens degrade gracefully when the hub is unreachable.
 * We re-bind HubClient to a stub that always throws HubUnreachableException.
 */
class HubErrorSurfaceTest extends TestCase
{
    use RefreshDatabase;

    /** @var HubClient&MockObject */
    private HubClient $stub;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stub = $this->createMock(HubClient::class);
        $this->stub->method('summary')->willThrowException(HubUnreachableException::unconfigured());
        $this->stub->method('targets')->willThrowException(HubUnreachableException::unconfigured());
        $this->stub->method('target')->willThrowException(HubUnreachableException::unconfigured());
        $this->stub->method('alerts')->willThrowException(HubUnreachableException::unconfigured());
        $this->stub->method('logs')->willThrowException(HubUnreachableException::unconfigured());
        $this->stub->method('apps')->willThrowException(HubUnreachableException::unconfigured());

        $this->app->instance(HubClient::class, $this->stub);
    }

    public function test_dashboard_renders_successfully_when_hub_unreachable(): void
    {
        Livewire::test(Dashboard::class)
            ->assertSuccessful()
            ->assertSee('Hub unreachable');
    }

    public function test_dashboard_shows_settings_hint_when_unconfigured(): void
    {
        Livewire::test(Dashboard::class)
            ->assertSee('Settings');
    }

    public function test_target_list_renders_successfully_when_hub_unreachable(): void
    {
        Livewire::test(TargetList::class)
            ->assertSuccessful()
            ->assertSee('Hub unreachable');
    }

    public function test_target_detail_renders_successfully_when_hub_unreachable(): void
    {
        Livewire::test(TargetDetail::class, ['targetId' => 'pve'])
            ->assertSuccessful()
            ->assertSee('Hub unreachable');
    }

    public function test_app_list_renders_successfully_when_hub_unreachable(): void
    {
        Livewire::test(AppList::class)
            ->assertSuccessful()
            ->assertSee('Hub unreachable');
    }

    public function test_log_search_renders_successfully_when_hub_unreachable(): void
    {
        Livewire::test(LogSearch::class)
            ->assertSuccessful()
            ->assertSee('Hub unreachable');
    }

    public function test_alert_list_renders_successfully_when_hub_unreachable(): void
    {
        Livewire::test(AlertList::class)
            ->assertSuccessful()
            ->assertSee('Hub unreachable');
    }
}
