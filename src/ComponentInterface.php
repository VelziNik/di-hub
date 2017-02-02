<?php

namespace Nayjest\DI;

interface ComponentInterface
{
    public function register(ComponentDefinitions $definitions);

    public function setHub(Hub $hub);
}
