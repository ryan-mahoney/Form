<?php
namespace Opine\Form;

class TestService
{
    private $post;

    public function __construct($post)
    {
        $this->post = $post;
    }

    public function fakeSubmit($context)
    {
        $this->post->statusSaved();
    }
}
