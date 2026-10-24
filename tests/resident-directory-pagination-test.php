<?php
$source = file_get_contents(__DIR__ . '/../includes/views/residents.php');
$start = strpos($source, '$residentCount =');
$end = strpos($source, '$residentCardIcons =', $start);
$pagination = substr($source, $start, $end - $start);
$locationView = 'resident-management';
$adminResidents = array_map(static fn($id) => ['id'=>$id], range(1, 45));
$_GET = ['resident_page'=>'2','resident_search'=>'Deloy','account_filter'=>'Active','district_filter'=>'1','purok_filter'=>'2','unexpected'=>'ignored'];
eval($pagination);
if (count($residentPageRows) !== 20 || $residentPageRows[0]['id'] !== 21) throw new RuntimeException('Incorrect page slice');
parse_str(ltrim($residentPageLink(3), '?'), $query);
if ($query['view'] !== 'resident-management' || $query['resident_search'] !== 'Deloy' || $query['purok_filter'] !== '2' || isset($query['unexpected'])) throw new RuntimeException('Pagination lost filters or role route');
$_GET['resident_page'] = 999;
eval($pagination);
if ($residentPage !== 3 || count($residentPageRows) !== 5) throw new RuntimeException('Last page was not clamped');
$adminResidents = [];
eval($pagination);
if ($residentPage !== 1 || $residentPageCount !== 1 || $residentPageRows !== []) throw new RuntimeException('Empty list has invalid pagination');
echo "PASS: resident pages limit cards, preserve filters and routes, and handle empty/out-of-range pages.\n";
