<?php

class DeleteMe
{
    public function first($a, $b)
    {
        return $a+$b;
    }

    public function second($items)
    {
        $total = 0;
        foreach ($items as $item) {
            $total += $item->price();
        }

        return $total;
    }

    public function third($name)
    {
        return strtoupper($name);
    }
}
