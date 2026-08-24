<?php

// ag: Confirms login works and redirects properly.
namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class LoginPageTest extends WebTestCase
{
    public function testLoginPageLoads(): void
    {
        $client = static::createClient();

        // Request login page
        $client->request('GET', '/login');

        // Page should load
        $this->assertSelectorExists('form'); // form is present
        $this->assertSelectorExists('input[name="_username"]');
        $this->assertSelectorExists('input[name="_password"]');
    }


    public function testLoginWithValidCredentials(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/login');

        $form = $crawler->selectButton('Sign in')->form([
            '_username' => 'agarrido84+admin@gmail.com',
            '_password' => 'supersecret',
        ]);
        $client->submit($form);

        // first redirect to post-login
        $this->assertResponseRedirects('/post-login');
        $client->followRedirect();
        $this->assertSame('app_post_login', $client->getRequest()->attributes->get('_route'));

        // second redirect to the dashboard selected in post_login
        $client->followRedirect();
        $this->assertSame('admin_dashboard', $client->getRequest()->attributes->get('_route'));

        $this->assertSelectorExists('#admin-dashboard-view-table');
    }

    public function testLoginWithInvalidCredentials(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/login');

        $form = $crawler->selectButton('Sign in')->form([
            '_username' => 'wrong@example.test',
            '_password' => 'badpass',
        ]);
        $client->submit($form);

        // Should redirect back to login
        $this->assertResponseRedirects('/login');
        $client->followRedirect();

        // Look for your error flash
        $this->assertSelectorExists('.alert-danger');
    }
}
