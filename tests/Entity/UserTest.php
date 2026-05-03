<?php

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testUserCreation(): void
    {
        $user = new User();
        $user->setUsername('Chaima');
        $user->setEmail('chaima@test.com');

        $this->assertEquals('Chaima', $user->getUsername());
        $this->assertEquals('chaima@test.com', $user->getEmail());
        $this->assertEquals('Chaima', $user->getUserIdentifier());
        $this->assertInstanceOf(\DateTimeImmutable::class, $user->getCreatedAt());
    }

    public function testUserRoles(): void
    {
        $user = new User();
        $this->assertContains('ROLE_USER', $user->getRoles());

        $user->setRoles(['ROLE_ADMIN']);
        $this->assertContains('ROLE_ADMIN', $user->getRoles());
        $this->assertContains('ROLE_USER', $user->getRoles());
    }

    public function testUserProfilePhoto(): void
    {
        $user = new User();
        $this->assertNull($user->getProfilePhotoPath());

        $user->setProfilePhotoPath('avatar.jpg');
        $this->assertEquals('avatar.jpg', $user->getProfilePhotoPath());
    }

    public function testUserToString(): void
    {
        $user = new User();
        $user->setUsername('TestUser');
        $this->assertEquals('TestUser', (string) $user);
    }

    public function testUserDefaults(): void
    {
        $user = new User();
        $this->assertNotNull($user->getCreatedAt());
        $this->assertContains('ROLE_USER', $user->getRoles());
    }
}
