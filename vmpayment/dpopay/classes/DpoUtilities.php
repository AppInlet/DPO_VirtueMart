<?php

namespace DpoPay;

use SimpleXMLElement;
use Exception;

class DpoUtilities
{
    /**
     * @param string $verify
     * @return SimpleXMLElement|string
     * @throws Exception
     */
    public function getSimpleXMLElement(string $verify): string|SimpleXMLElement
    {
        if (!empty($verify) && str_starts_with($verify, '<?xml')) {
            try {
                $verify = new SimpleXMLElement($verify);
            } catch (Exception $e) {
                return $verify;
            }
        }
        return $verify;
    }
}
