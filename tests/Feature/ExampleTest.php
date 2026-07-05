<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function test_guest_root_redirects_to_login()
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }
}
