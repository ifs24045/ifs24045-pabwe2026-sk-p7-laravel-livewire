<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\EnsureRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EnsureRoleTest extends TestCase
{
    use RefreshDatabase;

    private function requestFor(?User $user): Request
    {
        $request = Request::create('/uji');
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function handle(?User $user, string ...$roles): Response
    {
        return (new EnsureRole())->handle(
            $this->requestFor($user),
            fn () => new Response('lolos'),
            ...$roles
        );
    }

    private function assertForbidden(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Seharusnya melempar HttpException 403.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_allows_user_with_matching_role(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertSame('lolos', $this->handle($admin, User::ROLE_ADMIN)->getContent());
    }

    public function test_allows_user_when_role_is_one_of_several(): void
    {
        $member = User::factory()->create();

        $this->assertSame('lolos', $this->handle($member, User::ROLE_ADMIN, User::ROLE_MEMBER)->getContent());
    }

    public function test_rejects_user_with_other_role(): void
    {
        $member = User::factory()->create();

        $this->assertForbidden(fn () => $this->handle($member, User::ROLE_ADMIN));
    }

    public function test_rejects_guest(): void
    {
        $this->assertForbidden(fn () => $this->handle(null, User::ROLE_MEMBER));
    }
}