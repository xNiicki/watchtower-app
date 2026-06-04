<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\HubClient;
use App\Data\Target;
use App\Data\TargetStatus;
use App\Livewire\Infra\TargetDetail;
use App\Livewire\Infra\TargetList;
use App\Services\FakeHubClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InfraTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function lists_targets_grouped_with_status_indicators(): void
    {
        Livewire::test(TargetList::class)
            ->assertSee('pve')
            ->assertSee('mariadb-prod')
            ->assertSee('jelly');
    }

    #[Test]
    public function detail_screen_shows_metrics_and_host_logs(): void
    {
        Livewire::test(TargetDetail::class, ['targetId' => 'mariadb-prod'])
            ->assertSee('mariadb-prod')
            ->assertSee('91')                 // disk percent from fixture
            ->assertSee('Aborted connection'); // host-filtered log line
    }

    #[Test]
    public function detail_route_resolves(): void
    {
        $this->get(route('infra.show', 'booking'))->assertSuccessful();
    }

    #[Test]
    public function unknown_target_id_returns_404(): void
    {
        $this->get(route('infra.show', 'nope'))->assertNotFound();
    }

    #[Test]
    public function nodes_render_as_group_headers_not_child_rows(): void
    {
        $html = Livewire::test(TargetList::class)->html();

        $this->assertSame(1, substr_count($html, route('infra.show', 'pve')));
        $this->assertSame(1, substr_count($html, route('infra.show', 'immich')));
    }

    #[Test]
    public function storage_detail_shows_disk_usage_and_not_cpu_mem_labels(): void
    {
        Livewire::test(TargetDetail::class, ['targetId' => 'tank'])
            ->assertSee('71')             // diskPercent from fixture (71.4)
            ->assertSee('Disk usage')
            ->assertDontSee('cpu')
            ->assertDontSee('mem');
    }

    #[Test]
    public function non_storage_detail_shows_cpu_and_mem_grid(): void
    {
        Livewire::test(TargetDetail::class, ['targetId' => 'mariadb-prod'])
            ->assertSee('cpu')
            ->assertSee('mem');
    }

    #[Test]
    public function paused_targets_are_hidden_from_the_list(): void
    {
        // paperless-gpt and o3-test are Paused (disabled in the hub) in the fixture.
        $component = Livewire::test(TargetList::class);

        $component->assertDontSee('paperless-gpt');
        $component->assertDontSee('o3-test');

        $html = $component->html();
        $this->assertSame(0, substr_count($html, route('infra.show', 'paperless-gpt')));
        $this->assertSame(0, substr_count($html, route('infra.show', 'o3-test')));
    }

    #[Test]
    public function non_paused_targets_still_appear_in_the_list(): void
    {
        Livewire::test(TargetList::class)
            ->assertSee('mariadb-prod') // Up
            ->assertSee('jelly');       // Down
    }

    #[Test]
    public function empty_state_shows_when_all_targets_are_paused(): void
    {
        $this->app->instance(HubClient::class, new class extends FakeHubClient
        {
            public function targets(): Collection
            {
                return collect([
                    new Target('alpha', 'alpha', 'lxc', TargetStatus::Paused, 'pve'),
                    new Target('beta', 'beta', 'lxc', TargetStatus::Paused, 'pve'),
                ]);
            }
        });

        Livewire::test(TargetList::class)
            ->assertSee('No targets reported yet.')
            ->assertDontSee('alpha')
            ->assertDontSee('beta');
    }

    #[Test]
    public function infra_list_renders_storage_group_and_not_under_node(): void
    {
        $component = Livewire::test(TargetList::class);

        // The STORAGE header appears
        $component->assertSee('Storage');

        // tank is listed somewhere in the page
        $component->assertSee('tank');

        // tank does NOT appear as a child of the backup node group:
        // confirm there is only one link to tank's detail route
        $html = $component->html();
        $this->assertSame(1, substr_count($html, route('infra.show', 'tank')));
    }
}
