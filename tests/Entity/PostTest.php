<?php

namespace App\Tests\Entity;

use App\Entity\Comment;
use App\Entity\Post;
use App\Entity\Reaction;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class PostTest extends TestCase
{
    public function testPostCreation(): void
    {
        $post = new Post();
        $post->setDescription('Test description de voyage');
        $post->setLikes(0);

        $this->assertEquals('Test description de voyage', $post->getDescription());
        $this->assertEquals(0, $post->getLikes());
        $this->assertInstanceOf(\DateTimeInterface::class, $post->getDateCreation());
    }

    public function testIncrementLikes(): void
    {
        $post = new Post();
        $post->setLikes(5);
        $post->incrementLikes();

        $this->assertEquals(6, $post->getLikes());
    }

    public function testPostAuteurRelation(): void
    {
        $user = new User();
        $user->setUsername('TestUser');

        $post = new Post();
        $post->setAuteur($user);

        $this->assertSame('TestUser', $post->getAuteur());
    }

    public function testAddComment(): void
    {
        $post = new Post();
        $comment = new Comment();

        $post->addComment($comment);

        $this->assertCount(1, $post->getComments());
        $this->assertSame($post, $comment->getPost());
    }

    public function testAddReaction(): void
    {
        $post = new Post();
        $reaction = new Reaction();

        $post->addReaction($reaction);

        $this->assertCount(1, $post->getReactions());
        $this->assertEquals(1, $post->getTotalReactions());
    }

    public function testRemoveComment(): void
    {
        $post = new Post();
        $comment = new Comment();

        $post->addComment($comment);
        $this->assertCount(1, $post->getComments());

        $post->removeComment($comment);
        $this->assertCount(0, $post->getComments());
    }

    public function testGetReactionCounts(): void
    {
        $post = new Post();

        $user1 = new User();
        $user1->setUsername('UserA');
        $user2 = new User();
        $user2->setUsername('UserB');

        $r1 = new Reaction();
        $r1->setReactionType('❤️');
        $r1->setUser($user1);
        $post->addReaction($r1);

        $r2 = new Reaction();
        $r2->setReactionType('❤️');
        $r2->setUser($user2);
        $post->addReaction($r2);

        $r3 = new Reaction();
        $r3->setReactionType('😂');
        $r3->setUser($user1);
        $post->addReaction($r3);

        $counts = $post->getReactionCounts();
        $this->assertEquals(2, $counts['❤️']);
        $this->assertEquals(1, $counts['😂']);
    }

    public function testPostCheminPhoto(): void
    {
        $post = new Post();
        $post->setCheminPhoto('photo-test.jpg');

        $this->assertEquals('photo-test.jpg', $post->getCheminPhoto());
    }
}
