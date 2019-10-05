<?php
namespace wiggum\services\console;

class Output {
    
    protected $foreground;
    protected $background;
    protected $styles;
   
    private $foregroundCodes = [
        Console::BLACK         => '0;30',
        Console::DARK_GREY     => '1;30',
        Console::RED           => '0;31',
    	Console::LIGHT_RED	   => '1;31',
        Console::GREEN         => '0;32',
    	Console::LIGHT_GREEN   => '1;32',
    	Console::BROWN		   => '0;33',
        Console::YELLOW        => '1;33',
        Console::BLUE          => '0;34',
    	Console::LIGHT_BLUE    => '1;34',
        Console::MAGENTA       => '0;35',
    	Console::LIGHT_MAGENTA => '1;35',
        Console::CYAN          => '0;36',
    	Console::LIGHT_CYAN	   => '1;36',
        Console::LIGHT_GREY    => '0;37',
        Console::WHITE         => '1;37'
    ];
    
    private $backgroundCodes = [
		Console::BLACK         => '40',
	    Console::RED           => '41',
	    Console::GREEN         => '42',
	    Console::YELLOW        => '43',
	    Console::BLUE          => '44',
	    Console::MAGENTA       => '45',
	    Console::CYAN          => '46',
	    Console::LIGHT_GREY    => '47'
	];
    
    private $styleCodes = [
        'bold'          => '1',
        'dim'           => '2',
        'underline'     => '4',
        'blink'         => '5',
        'invert'        => '7',
        'hidden'        => '8',
    ];

    /**
     * 
     */
    public function __construct()
    {
        $this->foreground = '';
        $this->background = '';
        $this->styles = [];
    }
    
    /**
     * 
     * @param string $color
     * @return Output
     */
    public function color(string $color): Output
    {
        $this->foreground = isset($this->foregroundCodes[$color]) ? $this->foregroundCodes[$color] : '';
        
        return $this;
    }
    
    /**
     * 
     * @param string $color
     * @return Output
     */
    public function background(string $color): Output
    {
        $this->background = isset($this->backgroundCodes[$color]) ? $this->backgroundCodes[$color] : '';
        
        return $this;
    }
    
    /**
     * 
     * @return Output
     */
    public function bold(): Output
    {
        $this->styles[] = 'bold';
        
        return $this;
    }
    
    /**
     * 
     * @return Output
     */
    public function dim(): Output
    {
        $this->styles[] = 'dim';
        
        return $this;
    }
    
    /**
     * 
     * @return Output
     */
    public function underline(): Output
    {
        $this->styles[] = 'underline';
        
        return $this;
    }
    
    /**
     * 
     * @return Output
     */
    public function blink(): Output
    {
        $this->styles[] = 'blink';
        
        return $this;
    }
    
    /**
     * 
     * @return Output
     */
    public function invert(): Output
    {
        $this->styles[] = 'invert';
        
        return $this;
    }
    
    /**
     * 
     * @return Output
     */
    public function hidden(): Output
    {
        $this->styles[] = 'hidden';
        
        return $this;
    }
    
    /**
     *
     * @param string $str
     * @param bool $newline
     */
    public function write(string $str, bool $newline = true): void
    {
        
        $styled = '';
        
        // attach colors
        if (!empty($this->foreground) || !empty($this->background)) {
            $colors = implode(';', array_filter([$this->foreground, $this->background]));
            $styled .= "\e[" . $colors . "m" ;
        }
        
        // attach styles
        foreach ($this->styles as $style) {
            $styled .= "\e[".$this->styleCodes[$style]."m";
        }
        
        // attach output string
        $styled .= $str . "\e[0m";
        
        // attach new line option
        $styled .= $newline ? "\n" : '';
        
        fwrite(STDOUT, $styled);
    }
    
    /**
     * 
     * @param string $str
     */
    public function error(string $str): void
    {
     	$this->foreground = $this->foregroundCodes[Console::LIGHT_RED];
         
         $this->write($str);
     }
     
     /**
      * 
      * @param string $str
      */
     public function comment(string $str): void
     {
        $this->foreground = $this->foregroundCodes[Console::BROWN];
         
        $this->write($str);
     }
     
     /**
      * 
      * @param string $str
      */
     public function whisper(string $str): void
     {
     	$this->foreground = $this->foregroundCodes[Console::LIGHT_GREY];
         
        $this->write($str);
     }
     
     /**
      * 
      * @param string $str
      */
     public function shout(string $str): void
     {
        $this->foreground = $this->foregroundCodes[Console::RED];
         
        $this->write($str);
     }
     
     /**
      * 
      * @param string $str
      */
     public function info(string $str): void
     {
         $this->foreground = $this->foregroundCodes[Console::GREEN];
         
         $this->write($str);
     }
    
}