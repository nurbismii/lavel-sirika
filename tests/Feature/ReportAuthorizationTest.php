<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function report_routes_are_mapped_to_admin_hr_and_auditor_roles()
    {
        $expected = [
            User::ROLE_ADMIN_HR,
            User::ROLE_AUDITOR,
        ];

        $this->assertSame($expected, User::rolesForRoute('reports.permits.index'));
        $this->assertSame($expected, User::rolesForRoute('reports.permits.export'));
        $this->assertSame($expected, User::rolesForRoute('reports.scans.index'));
        $this->assertSame($expected, User::rolesForRoute('reports.scans.export'));
    }

    /** @test */
    public function report_navigation_access_follows_report_roles()
    {
        $admin = $this->user(User::ROLE_ADMIN_HR);
        $auditor = $this->user(User::ROLE_AUDITOR);
        $security = $this->user(User::ROLE_SECURITY);
        $superAdmin = $this->user(User::ROLE_SUPER_ADMIN);

        foreach (['reports.permits.index', 'reports.scans.index'] as $routeName) {
            $this->assertTrue($admin->canAccessRoute($routeName));
            $this->assertTrue($auditor->canAccessRoute($routeName));
            $this->assertTrue($superAdmin->canAccessRoute($routeName));
            $this->assertFalse($security->canAccessRoute($routeName));
        }
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => User::STATUS_ACTIVE,
        ]);
    }
}
