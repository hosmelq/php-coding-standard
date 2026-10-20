<?php
$command = ['ffmpeg', '-i', $input, // source
    '-crf', '23', '-preset', 'slow', $output];
$args = [ // ffmpeg arguments
    '-a', 'cq-level=23', // constant quality
    '-b', 'x', // other
];
$call = run($first, // why
    $second);
$done = [
    'a', // first
    'b', // second
];

-----
<?php
$command = [
    'ffmpeg',
    '-i',
    $input, // source
    '-crf',
    '23',
    '-preset',
    'slow',
    $output,
];
$args = [ // ffmpeg arguments
    '-a',
    'cq-level=23', // constant quality
    '-b',
    'x', // other
];
$call = run(
    $first, // why
    $second,
);
$done = [
    'a', // first
    'b', // second
];
