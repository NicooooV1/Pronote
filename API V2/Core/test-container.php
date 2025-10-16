<?php
require_once 'API/Core/Container.php';

class TestService {
    public function greet() { return "Hello"; }
}

class UserService {
    private $test;
    public function __construct(TestService $test) {
        $this->test = $test;
    }
    public function getMessage() {
        return $this->test->greet() . " User";
    }
}

$container = new \Pronote\Core\Container();
$container->singleton(TestService::class);
$container->bind(UserService::class);

$user = $container->make(UserService::class);
echo $user->getMessage(); // Doit afficher "Hello User"