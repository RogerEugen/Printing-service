<?php

it('does not expose email verification routes', function () {
    $this->get('/verify-email')->assertNotFound();
    $this->post('/email/verification-notification')->assertNotFound();
});
