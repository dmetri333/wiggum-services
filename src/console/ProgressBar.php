<?php
namespace wiggum\services\console;

class ProgressBar {
    
    private $units;
    private $index;
    
    /**
     * 
     * @param int $units
     */
    public function __construct(int $units)
    {
        $this->units = $units;
        $this->index = 0;
    }
    
    /**
     * 
     */
    public function start(): void
    {
        fwrite(STDERR, "\0337");
    }
    
    /**
     * 
     */
    public function advance(): void
    {
        $step = intval($this->index / 10);
        fwrite(STDERR, "\0338");
        fwrite(STDERR, "[". str_repeat("#", $step) . str_repeat(".", 10 - $step) . "]   " . $this->index . "% Complete" );
        fwrite(STDERR, "\033[1A");
        
        $this->index++;
    }
    
    /**
     * 
     */
    public function finish() {
      
    }

}