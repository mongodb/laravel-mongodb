<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

class Options
{
    private string $option1;
    private string $option2;

    public function setOption1(string $option1): self
    {
        $this->option1 = $option1;
        return $this;
    }

    public function setOption2(string $option2): self
    {
        $this->option2 = $option2;
        return $this;
    }

    public function serialize(): object
    {
        $result = [];
        if (isset($this->option1)) {
            $result['option1'] = $this->option1;
        }

        if (isset($this->option2)) {
            $result['option2'] = $this->option2;
        }

        return (object) $result;
    }
}
