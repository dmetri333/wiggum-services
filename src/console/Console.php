<?php
namespace wiggum\services\console;

class Console {
    
    const BLACK         = 'black';
    const DARK_GREY     = 'dark_grey';
    const RED           = 'red';
    const LIGHT_RED     = 'light_red';
    const GREEN         = 'green';
    const LIGHT_GREEN   = 'light_green';
    const BROWN         = 'brown';
    const YELLOW        = 'yellow';
    const BLUE          = 'blue';
    const LIGHT_BLUE    = 'light_blue';
    const MAGENTA       = 'magenta';
    const LIGHT_MAGENTA = 'light_magenta';
    const CYAN          = 'cyan';
    const LIGHT_CYAN    = 'light_cyan';
    const LIGHT_GREY    = 'light_grey';
    const WHITE         = 'white';
    
    /**
     * 
     * @return \wiggum\services\console\Input
     */
    public function input()
    {
        return new Input();
    }
    
    /**
     * 
     * @return \wiggum\services\console\Output
     */
    public function output()
    {
        return new Output();
    }    
    
    /**
     * 
     * @param int $units
     * @return \wiggum\services\console\ProgressBar
     */
    public function progressBar(int $units)
    {
        return new ProgressBar($units);
    }
    
}