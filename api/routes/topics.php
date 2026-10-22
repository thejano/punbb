<?php

api_route('GET', '/topics/{id}', function ($p) {
	api_json(api_topic_shape(api_topic_or_404((int)$p['id'])));
});
