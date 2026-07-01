#!/usr/bin/env php
<?php

require(__DIR__ . '/../include/cli_check.php');
require_once($config['base_path'] . '/lib/api_tree.php');

if ($config['poller_id'] > 1) {
	db_switch_remote_to_main();
}

$tree_id = 1;

$branches = array(
	'R225-MKA',
	'P3',
	'_GATEWAY_',
	'P5',
	'P6',
	'P12',
	'PEKANBARU',
	'PALU',
	'BALI',
	'BANJARMASIN',
	'BATAM',
	'SEMARANG',
	'MAKASSAR',
	'MUARABARU',
	'PALEMBANG',
	'MEDAN',
	'MANADO',
	'SURABAYA',
	'BALIKPAPAN',
);

/* Map device description -> branch name (from CSV analysis) */
$desc_to_branch = array(
	'Azure - DEVDB - 10.0.0.10'                   => '_GATEWAY_',
	'AZURE - KIATVNET'                             => '_GATEWAY_',
	'Bacbone-1-Balikpapan-(R-ADMIN)'               => 'BALIKPAPAN',
	'Bacbone-1-LT2-Semarang'                       => 'SEMARANG',
	'Bacbone-2-Balikpapan-(LANTAI 2)'              => 'BALIKPAPAN',
	'Bacbone-3-Balikpapan-(LANTAI 3)'              => 'BALIKPAPAN',
	'Bacbone-3-Semarang-(R-ADMIN)'                 => 'SEMARANG',
	'Bacbone-4-Balikpapan-Battery'                 => 'BALIKPAPAN',
	'Bekasi - Router Public ISP Quantum - Segment - 1' => 'P5',
	'Bekasi - Router Public ISP Quantum - Segment - 2' => 'P5',
	'CS_1-AREA-DISTRIBUSI-6_BPP'                   => 'BALIKPAPAN',
	'CS1_AREA-DISTRIBUSI_5-BPP'                    => 'BALIKPAPAN',
	'Distribusi_1 Sw Palu'                         => 'PALU',
	'Distribusi_2 SW Palu'                         => 'PALU',
	'DISTRIBUSI-8_OFFBLK-BPP'                      => 'BALIKPAPAN',
	'DISTRIBUSI4_UNLOADING_BPP'                    => 'BALIKPAPAN',
	'DRY-LT1-DISTRIBUSI_3-BPP'                     => 'BALIKPAPAN',
	'DRY-LT1-DISTRIBUSI2_BPP'                      => 'BALIKPAPAN',
	'GW - Makassar - ASTINET'                      => 'MAKASSAR',
	'GW - P5 - Quantum'                            => 'P5',
	'GW - P5 - Remala'                             => 'P5',
	'GW - P6 - REMALA_Link Backup'                 => 'P6',
	'Local Linux Machine'                          => '_GATEWAY_',
	'Local Windows Machine'                        => '_GATEWAY_',
	'Mikrotik - Bali - R243'                       => 'BALI',
	'Mikrotik - Balikpapan - GW - R208'            => 'BALIKPAPAN',
	'Mikrotik - Balikpapan - SW Core'              => 'BALIKPAPAN',
	'Mikrotik - Banjarmasin - R210'                => 'BANJARMASIN',
	'Mikrotik - BATAM - R201'                      => 'BATAM',
	'Mikrotik - GW - Bekasi - Wh-100 - R222'       => 'P5',
	'Mikrotik - GW - Muarabaru - Quantum - RAS'    => 'MUARABARU',
	'Mikrotik - GW - P12 - Smart A - R172 - DRC - A' => 'P12',
	'Mikrotik - GW - Tambaksawah - Quantum - R242' => 'SURABAYA',
	'Mikrotik - Makassar - R237 (Pattene)'         => 'MAKASSAR',
	'Mikrotik - Makassar - R239'                   => 'MAKASSAR',
	'Mikrotik - Manado'                            => 'MANADO',
	'Mikrotik - Medan - R212'                      => 'MEDAN',
	'Mikrotik - Muara Baru - R240-KAG-RAS'         => 'MUARABARU',
	'Mikrotik - P12 - R206-TIS'                    => 'P12',
	'Mikrotik - P12 - R221-6 Distribusi Scan AS'   => 'P12',
	'Mikrotik - P12 - R221-AS'                     => 'P12',
	'Mikrotik - P12 - R234-KAG-CILEUNGSI'          => 'P12',
	'Mikrotik - P3 - R203 - MKA'                   => 'P3',
	'Mikrotik - P5 - Distribusi AP Learning Center' => 'P5',
	'Mikrotik - P5 - DISTRIBUSI AP SCAN WH-100'    => 'P5',
	'Mikrotik - P5 - SMART'                        => 'P5',
	'Mikrotik - P5 - Switch-Public-ip Distribusi'  => 'P5',
	'Mikrotik - P6 - R228 - Fase 3'                => 'P6',
	'Mikrotik - P6 - R229'                         => 'P6',
	'Mikrotik - P6 - R232-L3'                      => 'P6',
	'Mikrotik - P6-2 - R227'                       => 'P6',
	'Mikrotik - Palembang - R204'                  => 'PALEMBANG',
	'Mikrotik - Palu - R211'                       => 'PALU',
	'Mikrotik - Pekanbaru - R202'                  => 'PEKANBARU',
	'Mikrotik - Pontianak - R207'                  => '_GATEWAY_',
	'Mikrotik - Semarang - R205'                   => 'SEMARANG',
	'Mikrotik - Semarang- Switch Core'             => 'SEMARANG',
	'Mikrotik - Surabaya - Krian - R223'           => 'SURABAYA',
	'Mikrotik - Surabaya - Krian - R223 - Linknet' => 'SURABAYA',
	'Mikrotik - Surabaya - Tambaksawah - R242'     => 'SURABAYA',
	'MIKROTIK PUBLIC IP MAKASSAR'                  => 'MAKASSAR',
	'MTC-DISTRIBUSI-7_10-BPP'                      => 'BALIKPAPAN',
	'P5 - REMALA - 101.255.56.181'                 => 'P5',
	'P5 Server Gate'                               => 'P5',
	'R. CCTV - NVR 4'                              => 'P12',
	'R225 - P5 MKA'                                => 'R225-MKA',
	'SBY - KRIAN (ASTINET - REG)'                  => 'SURABAYA',
	'SBY - KRIAN (ASTINET)'                        => 'SURABAYA',
	'Sw_Core Palu'                                 => 'PALU',
	'Ubiquiti AP U6 - Semarang - ADM'              => 'SEMARANG',
	'Ubiquiti AP U6 - Semarang - ANTE'             => 'SEMARANG',
	'Ubiquiti AP U6 - Semarang - ANTE1-B'          => 'SEMARANG',
	'Ubiquiti AP U6 - Semarang - CHILLER2'         => 'SEMARANG',
	'Ubiquiti AP U6 - Semarang - CS1-A'            => 'SEMARANG',
	'Ubiquiti AP U6 - Semarang - CS1-B'            => 'SEMARANG',
	'Ubiquiti AP U6 - Semarang - CS3-B'            => 'SEMARANG',
	'Ubiquiti AP U6 - Semarang - OFFICE-LT2-A'     => 'SEMARANG',
	'Ubiquiti AP U6 - Semarang - OFFICE-LT2-B'     => 'SEMARANG',
	'Ubiquiti AP U6 - Semarang - OFFICE-LT3'       => 'SEMARANG',
	'VITAL - 192.168.220.6'                        => '_GATEWAY_',
	'WEB - gp.kiatananda.com'                      => '_GATEWAY_',
	'web - kacustomercare.com'                     => '_GATEWAY_',
	'web - kiatapp.kiatananda.com'                 => '_GATEWAY_',
	'web - smart.kacoldstorage.com'                => '_GATEWAY_',
);

/* Fetch all hosts from DB and map by description */
echo "Mapping devices from database...\n";
$all_hosts = db_fetch_assoc('SELECT id, description, hostname FROM host ORDER BY id');
$host_to_branch = array();
$unmapped_descs = array();

foreach ($all_hosts as $h) {
	$desc = trim($h['description']);
	if (isset($desc_to_branch[$desc])) {
		$host_to_branch[$h['id']] = $desc_to_branch[$desc];
	} else {
		$unmapped_descs[] = $desc;
	}
}

if (cacti_sizeof($unmapped_descs)) {
	echo "WARNING: These descriptions could not be mapped to a branch:\n";
	foreach ($unmapped_descs as $d) {
		echo "  - \"$d\"\n";
	}
}

/* Verify tree exists */
$tree = db_fetch_row_prepared('SELECT * FROM graph_tree WHERE id = ?', array($tree_id));
if (!$tree) {
	echo "ERROR: Tree ID $tree_id not found.\n";
	exit(1);
}

echo "Tree: {$tree['name']} (ID: $tree_id)\n";
echo "Total devices in DB: " . cacti_sizeof($all_hosts) . "\n";
echo "Mapped devices: " . cacti_sizeof($host_to_branch) . "\n\n";

/* Set root sort type to manual */
db_execute_prepared('UPDATE graph_tree SET sort_type = 1 WHERE id = ?', array($tree_id));

/* First, remove any existing tree items to start clean */
$existing_items = db_fetch_cell_prepared('SELECT COUNT(*) FROM graph_tree_items WHERE graph_tree_id = ?', array($tree_id));
if ($existing_items > 0) {
	echo "Removing $existing_items existing items from tree...\n";
	db_execute_prepared('DELETE FROM graph_tree_items WHERE graph_tree_id = ?', array($tree_id));
}

echo "\nCreating tree structure...\n";
echo str_repeat('-', 70) . "\n";

$branch_ids = array();
$ok_total   = 0;
$skip_total = 0;

foreach ($branches as $position => $branch_name) {
	$seq = $position + 1;

	$bid = api_tree_item_save(
		0, $tree_id, TREE_ITEM_TYPE_HEADER, 0,
		$branch_name, 0, 0, 0,
		HOST_GROUPING_GRAPH_TEMPLATE,
		TREE_ORDERING_NONE,
		false
	);

	if ($bid) {
		db_execute_prepared('UPDATE graph_tree_items SET position = ? WHERE id = ?', array($seq, $bid));
		$branch_ids[$branch_name] = $bid;
		echo "  [BRANCH] '$branch_name' created (ID: $bid, position: $seq)\n";
	} else {
		echo "  [ERROR] Failed to create branch '$branch_name'\n";
	}
}

echo str_repeat('-', 70) . "\n";
echo "Adding devices to branches...\n\n";

$branch_hosts = array();
foreach ($host_to_branch as $hid => $branch) {
	if (!isset($branch_hosts[$branch])) {
		$branch_hosts[$branch] = array();
	}
	$branch_hosts[$branch][] = $hid;
}

foreach ($branches as $branch_name) {
	if (!isset($branch_ids[$branch_name])) {
		continue;
	}
	$branch_id = $branch_ids[$branch_name];

	if (!isset($branch_hosts[$branch_name]) || !cacti_sizeof($branch_hosts[$branch_name])) {
		continue;
	}

	$hids = $branch_hosts[$branch_name];
	echo "--- $branch_name (" . count($hids) . " devices) ---\n";

	$pos = 1;
	foreach ($hids as $hid) {
		$host_info = db_fetch_row_prepared(
			'SELECT id, description, hostname FROM host WHERE id = ?',
			array($hid)
		);

		if (!$host_info) {
			continue;
		}

		$result = api_tree_item_save(
			0, $tree_id, TREE_ITEM_TYPE_HOST, $branch_id,
			'', 0, $host_info['id'], 0,
			HOST_GROUPING_GRAPH_TEMPLATE,
			TREE_ORDERING_INHERIT,
			false
		);

		if ($result === false) {
			echo "  - [SKIP] #{$host_info['id']} {$host_info['description']} (already exists)\n";
			$skip_total++;
		} elseif ($result > 0) {
			db_execute_prepared('UPDATE graph_tree_items SET position = ? WHERE id = ?', array($pos, $result));
			echo "  - [OK]   #{$host_info['id']} {$host_info['description']}\n";
			$ok_total++;
			$pos++;
		}
	}

	echo "\n";
}

/* Re-sort branches */
echo "Applying sort order...\n";
foreach ($branch_ids as $bid) {
	api_tree_sort_branch($bid, $tree_id);
}
api_tree_sort_branch(0, $tree_id);

/* Cleanup */
if (file_exists(__DIR__ . '/list_hosts.php')) {
	unlink(__DIR__ . '/list_hosts.php');
}

echo str_repeat('=', 70) . "\n";
echo "RECONSTRUCTION COMPLETE\n";
echo "  Branches: " . cacti_sizeof($branch_ids) . "\n";
echo "  Devices added:   $ok_total\n";
echo "  Devices skipped: $skip_total\n";
echo str_repeat('=', 70) . "\n";
