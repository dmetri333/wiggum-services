<?php
include 'Console.php';
include 'Input.php';
include 'Output.php';
include 'ProgressBar.php';

$console = new Console();

$console->input()->prompt('What is your name?');
// What is your name?

$console->input()->accept(['Fine', 'Ok'])->prompt('How you doin?');
// How you doin?

$console->input()->accept(['Fine', 'Ok'], true)->prompt('How you doin?');
// How you doin? [Fine/Ok]

$console->input()->confirm()->prompt('Continue?');
// Continue? [y/n]

$console->input()->password()->prompt('Please enter password:');
// Please enter password:


$console->input()->multiLine()->prompt('>>>');  // Will wait for ^D before returning
// >>>

$console->output()->write('Hello World! - clean');

$console->output()->error('Hello World! - error');

$console->output()->comment('Hello World! - comment');

$console->output()->whisper('Hello World! - whisper');

$console->output()->shout('Hello World! - shout');

$console->output()->info('Hello World! - info');

$console->output()->color(Console::BLACK)->write('Black! - color');
$console->output()->color(Console::DARK_GREY)->write('Dark grey! - color');
$console->output()->color(Console::RED)->write('Red! - color');
$console->output()->color(Console::LIGHT_RED)->write('Light red! - color');
$console->output()->color(Console::GREEN)->write('Green! - color');
$console->output()->color(Console::LIGHT_GREEN)->write('Light green! - color');
$console->output()->color(Console::BROWN)->write('Brown! - color');
$console->output()->color(Console::YELLOW)->write('Yellow! - color');
$console->output()->color(Console::BLUE)->write('Blue! - color');
$console->output()->color(Console::LIGHT_BLUE)->write('Light blue! - color');
$console->output()->color(Console::MAGENTA)->write('Mangenta! - color');
$console->output()->color(Console::LIGHT_MAGENTA)->write('Light Magenta! - color');
$console->output()->color(Console::CYAN)->write('Cyan! - color');
$console->output()->color(Console::LIGHT_CYAN)->write('Light cyan! - color');
$console->output()->color(Console::LIGHT_GREY)->write('Light grey! - color');
$console->output()->color(Console::WHITE)->write('White! - color');

$console->output()->background(Console::GREEN)->write('Hello World! - background');

$console->output()->bold()->write('Hello World! - bold');

$console->output()->dim()->write('Hello World! - dim');

$console->output()->underline()->write('Hello World! - underline');

$console->output()->blink()->write('Hello World! - blink');

$console->output()->invert()->write('Hello World! - invert');

$console->output()->hidden()->write('Hello World! - hidden');

$console->output()
	->background(Console::BLUE)
	->color(Console::GREEN)
	->blink()
	->write('Hello World! - color | background | blink');




$users = array_fill(0, 10, 'banana');


$bar = $console->progressBar(count($users), 10);
$bar->start();

foreach ($users as $user) {
	usleep(100000); //$this->performTask($user);
	
	$bar->advance();
}

$bar->finish();



$users = array_fill(0, 12, 'banana');


$bar = $console->progressBar(count($users), 12);
$bar->start();

foreach ($users as $user) {
	usleep(100000); //$this->performTask($user);
	
	$bar->advance();
}

$bar->finish();

