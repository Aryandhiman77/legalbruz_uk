<?php

namespace Tests\Feature;

use App\Mail\EmailOtpMail;
use App\Models\EmailOtp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailOtpAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_registration_keep_the_shared_header_and_footer(): void
    {
        foreach ([route('login'), route('register')] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('<nav class="navbar', false)
                ->assertSee('<footer class="site-footer">', false)
                ->assertSee('legal-bruz-ltd-logo.png', false)
                ->assertSee('css/auth.css', false);
        }
    }

    public function test_valid_password_starts_otp_challenge_without_logging_user_in(): void
    {
        Mail::fake();
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'password123',
        ]);

        $response = $this->post(route('login'), [
            'email' => 'LOGIN@example.com ',
            'password' => 'password123',
            'remember' => 'on',
        ]);

        $response->assertRedirect(route('auth.otp.show'));
        $this->assertGuest();
        $this->assertDatabaseCount('email_otps', 1);
        Mail::assertSent(EmailOtpMail::class, fn (EmailOtpMail $mail) => $mail->purpose === 'login');
    }

    public function test_correct_login_otp_authenticates_user_and_consumes_code(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create([
            'email' => 'login@example.com',
            'password' => 'password123',
        ]);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $code = null;
        Mail::assertSent(EmailOtpMail::class, function (EmailOtpMail $mail) use (&$code) {
            $code = $mail->code;

            return $mail->purpose === 'login';
        });

        $this->post(route('auth.otp.verify'), ['otp' => $code])
            ->assertRedirect('/home');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertNotNull(EmailOtp::first()->consumed_at);
    }

    public function test_incorrect_codes_are_limited_to_five_attempts(): void
    {
        Mail::fake();
        $user = User::factory()->create(['password' => 'password123']);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        foreach (range(1, 5) as $attempt) {
            $this->post(route('auth.otp.verify'), ['otp' => '000000'])
                ->assertSessionHasErrors('otp');
        }

        $this->assertGuest();
        $this->assertSame(5, EmailOtp::first()->attempts);
    }

    public function test_expired_otp_cannot_be_used(): void
    {
        Mail::fake();
        $user = User::factory()->create(['password' => 'password123']);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $code = null;
        Mail::assertSent(EmailOtpMail::class, function (EmailOtpMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        EmailOtp::first()->update(['expires_at' => now()->subSecond()]);

        $this->post(route('auth.otp.verify'), ['otp' => $code])
            ->assertSessionHasErrors('otp');

        $this->assertGuest();
    }

    public function test_otp_page_requires_a_pending_challenge(): void
    {
        $this->get(route('auth.otp.show'))
            ->assertRedirect(route('login'));
    }
}
