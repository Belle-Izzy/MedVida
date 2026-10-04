<?php

return [
    /*
     * Enquanto não houver provedor de pagamento, o ambiente local pode concluir
     * a taxa de adesão como uma simulação. Em produção, deixe como false:
     * uma clínica só deve ser ativada por um webhook validado do provedor.
     */
    'simular_pagamento_de_adesao' => env('MEDVIDA_SIMULAR_PAGAMENTO_DE_ADESAO', false),
];
