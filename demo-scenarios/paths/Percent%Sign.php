<?php

class PercentSign
{
    public function first($a, $b)
    {
        return $a+$b;
    }

    public function second($items)
    {
        foreach ($items as $item) {
            echo $item;
        }
    }

    public function third($name)
    {
        return 'Hello, ' . $name . '!';
    }
}
