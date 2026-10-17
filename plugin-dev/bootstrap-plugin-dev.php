#!/usr/bin/env php
<?php

$pharPath = $argv[1] ?? null;
$pluginSource = $argv[2] ?? getenv("PLUGIN_PATH");

if(!is_string($pharPath) || $pharPath === "" || !is_string($pluginSource) || $pluginSource === "") {
    echo "Usage: php bootstrap-plugin-dev.php <phar-path> <plugin-path>\n";
    exit(1);
}

$pluginPath = realpath($pluginSource);
if($pluginPath === false || !is_dir($pluginPath)) {
    echo "Cannot canonicalize plugin path: $pluginSource\n";
    exit(1);
}

$path = $pluginPath;
while(true) {
    $yamlPath = $path . "/.poggit.yml";
    if(is_file($yamlPath)) {
        break;
    }

    if(file_exists($path . "/.git")) {
        echo "Refusing to cross git repo boundary, exiting as success as no .poggit.yml detected\n";
        exit(0);
    }

    $newPath = dirname($path);
    if($newPath === $path) {
        echo "No .poggit.yml detected\n";
        exit(0);
    }

    $path = $newPath;
}

$yaml = yaml_parse_file($yamlPath);
if(!is_array($yaml)) {
    echo "Cannot parse $yamlPath\n";
    exit(1);
}

$project = null;

foreach(($yaml["projects"] ?? []) as $projectConfig) {
    $subpath = $projectConfig["path"] ?? ".";
    $projectPath = realpath($path . "/" . $subpath);

    if($projectPath !== false && $projectPath === $pluginPath) {
        $project = $projectConfig;
        break;
    }
}

if($project === null) {
    echo ".poggit.yml does not contain a project pointing to this plugin, exiting as success\n";
    exit(0);
}

foreach($project["libs"] ?? [] as $lib) {
    if(!isset($lib["src"], $lib["version"])) {
        echo "Invalid Virion library configuration\n";
        exit(1);
    }

    $src = $lib["src"];
    $version = $lib["version"];

    $split = explode("/", $src);
    if(count($split) === 2) {
        $src .= "/" . $split[1];
    }

    $cacheFile = ".cache/virion_cache_" . hash("fnv164", $src . "/" . $version) . ".phar";

    if(!file_exists($cacheFile)) {
        if(!is_dir(dirname($cacheFile)) && !mkdir(dirname($cacheFile), 0750, true) && !is_dir(dirname($cacheFile))) {
            echo "Cannot create cache directory\n";
            exit(1);
        }

        $cacheFh = fopen($cacheFile, "c");
        if($cacheFh === false) {
            echo "Cannot open cache file: $cacheFile\n";
            exit(1);
        }

        $curl = curl_init("https://poggit.pmmp.io/v.dl/$src/$version");
        curl_setopt($curl, CURLOPT_AUTOREFERER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_FILE, $cacheFh);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT_MS, 10000);
        curl_setopt($curl, CURLOPT_FAILONERROR, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ["User-Agent: pharynx-bootstrap/0.2"]);

        $ret = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);

        curl_close($curl);
        fclose($cacheFh);

        if($ret !== true || $httpCode < 200 || $httpCode >= 300) {
            @unlink($cacheFile);
            echo "Failed to download Virion $src/$version: $curlError\n";
            exit(1);
        }
    }

    echo "Shading $cacheFile into $pharPath\n";

    $command = implode(" ", array_map("escapeshellarg", [
        PHP_BINARY,
        "-dphar.readonly=0",
        $cacheFile,
        $pharPath,
    ]));

    passthru($command, $exitCode);

    if($exitCode !== 0) {
        exit($exitCode);
    }
}
