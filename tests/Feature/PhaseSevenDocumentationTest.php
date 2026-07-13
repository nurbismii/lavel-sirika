<?php

namespace Tests\Feature;

use Tests\TestCase;

class PhaseSevenDocumentationTest extends TestCase
{
    /** @test */
    public function phase_seven_security_documents_define_residual_risk_and_upgrade_exit_condition()
    {
        $inventory = file_get_contents(base_path('docs/security/SECURITY-EXPOSURE-INVENTORY.md'));
        $riskRegister = file_get_contents(base_path('docs/security/DEPENDENCY-RISK-REGISTER.md'));
        $readiness = file_get_contents(base_path('docs/upgrade/LARAVEL-12-READINESS.md'));

        $this->assertStringContainsString('Signed URL', $inventory);
        $this->assertStringContainsString('Tidak ditemukan penggunaan', $inventory);
        $this->assertStringContainsString('GHSA-crmm-hgp2-wgrp', $riskRegister);
        $this->assertStringContainsString('GHSA-5vg9-5847-vvmq', $riskRegister);
        $this->assertStringContainsString('GHSA-78fx-h6xr-vch4', $riskRegister);
        $this->assertStringContainsString('Pemilik penerimaan risiko:', $riskRegister);
        $this->assertStringContainsString('Tanggal review ulang:', $riskRegister);
        $this->assertStringContainsString('PHP 8.2', $readiness);
        $this->assertStringContainsString('Laravel 12', $readiness);
        $this->assertStringContainsString('sirika.vdnisite.com', $readiness);
    }

    /** @test */
    public function production_runbook_requires_explicit_dependency_risk_decision()
    {
        $runbook = file_get_contents(base_path('docs/deployment/CPANEL-PRODUCTION.md'));

        $this->assertStringContainsString('composer audit', $runbook);
        $this->assertStringContainsString('penerimaan risiko', $runbook);
        $this->assertStringContainsString('advisory baru', $runbook);
    }
}
