<?php

return [
    'v1_habilitado' => filter_var((string)env('ERP_V1_HABILITADO', 'true'), FILTER_VALIDATE_BOOLEAN),
];

