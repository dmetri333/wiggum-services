<?php
namespace wiggum\services\console;

class Input {
    
	private $accept;
	private $headsUp;
	private $password;
	private $multiLine;
	
	/**
	 * 
	 */
    public function __construct()
    {
        $this->accept = [];
        $this->headsUp = false;
        $this->password = false;
        $this->multiLine = false;
    }
    
    /**
     * 
     * @param array $options
     * @param bool $headsUp
     * @return Input
     */
    public function accept(array $options, bool $headsUp = false): Input
    {
    	$this->accept = $options; 
    	$this->headsUp = $headsUp; 
        
        return $this;
    }
    
    /**
     * 
     * @return Input
     */
    public function confirm(): Input
    {
    	$this->accept = ['y', 'n'];
    	$this->headsUp = true; 
        
        return $this;
    }
    
    /**
     * 
     * @return Input
     */
    public function password(): Input
    {
    	$this->password = true;
    	
        return $this;
    }
    
    /**
     * 
     * @return Input
     */
    public function multiLine(): Input
    {
    	$this->multiLine = true;
        
        return $this;
    }
    
    /**
     *
     * @param string $str
     * @return string
     */
    public function prompt(string $str): string
    {
        
        $acceptStr = '';
        if (!empty($this->accept) && $this->headsUp) {
            $acceptStr = ' ['.implode($this->accept, '/').']';
        }
        
        echo $str . $acceptStr;
        
        if ($this->multiLine) {
            
            $line = '';
            if ($this->multiLine) {
                while(($in = readline('')) != '.') {
                    $line .= (PHP_OS == 'WINNT') ? "\r\n".$in : $in;
                }
                echo $line;
            }
            
            return $line;
        }
        
        if ($this->password) {
            system('stty -echo');
        }
        
        $handle = fopen('php://stdin', 'r');
        $line = trim(fgets($handle));
        
        if ($this->password) {
            system('stty echo');
            echo "\n";
        }
        
        if (!empty($this->accept) && !in_array($line, $this->accept)) {
            $this->prompt($str);
            return;
        }
        
        return $line;
    }
    
}