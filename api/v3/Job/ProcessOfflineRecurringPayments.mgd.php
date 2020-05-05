<?php
return [
  0 => [
    'name' => 'Cron:Job.processofflinerecurringpayments',
    'entity' => 'Job',
    'update' => 'never',
    'params' => [
      'version' => 3,
      'name' => 'Job.processofflinerecurringpayments',
      'description' => 'Call Job.processofflinerecurringpayments API',
      'run_frequency' => 'Hourly',
      'api_entity' => 'Job',
      'api_action' => 'processofflinerecurringpayments',
      'parameters' => '',
    ],
  ],
];
