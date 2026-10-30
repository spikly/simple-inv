<?php

// Output is buffered, so the status can still be set.
http_response_code(404);

template('page/404');
