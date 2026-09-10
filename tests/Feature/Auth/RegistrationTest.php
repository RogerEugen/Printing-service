<?php

it('requires administrators to create company accounts', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register')->assertNotFound();
});
