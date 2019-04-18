<?php

echo_network("whoami");
function echo_network($cmd)
{
    system($cmd);
}