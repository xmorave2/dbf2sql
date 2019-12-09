<?php 

require __DIR__ . '/vendor/autoload.php';

use XBase\Table;
use XBase\Record;
use Ulrichsg\Getopt\Getopt;
use Ulrichsg\Getopt\Option;

function usage($errormessage = "error")
{
    global $argv;
    echo "\n$errormessage\n\n";
    echo "Usage: $argv[0] [-e encoding] [-b batchsize] [-d destinationdir] source_file [another_source_file [...]]\n\n";
    echo "Default encoding is utf-8, often used encoding in dbf files is CP1250, or CP1251.\n";
    echo "Default batch size is 1000 rows inserted at once.\n";
    echo "Default destination directory is source file's one.\n\n";
}

$encOption = new Option('e', 'encoding', Getopt::REQUIRED_ARGUMENT);
$batchOption = new Option('b', 'batchsize', Getopt::REQUIRED_ARGUMENT);
$destdirOption = new Option('d', 'destinationdir', Getopt::REQUIRED_ARGUMENT);
$getopt = new Getopt([$encOption, $batchOption, $destdirOption]);
$getopt->parse();
$encoding = $getopt["encoding"] ? $getopt["encoding"] : "UTF-8";
$batchSize = $getopt["batchsize"] ? $getopt["batchsize"] : 1000;
$destDir = $getopt["destinationdir"] ? $getopt["destinationdir"] : false;
$operands = $getopt->getOperands();

if (count($operands) == 0) {
    usage("Missing parameters");
    exit;
}

if ($destDir && !is_writable($destDir)) {
    echo "Destination directory {$destDir} does not exist or is not writable!\n";
    exit;
}

foreach ($operands as $sourcefile) {
    //TODO: make the code objective
    $pathInfo = pathinfo($sourcefile);
    $destinationfile = ($destDir ? $destDir : $pathInfo['dirname']) . "/" . $pathInfo['filename'] . ".sql";
    $destination = fopen($destinationfile, 'w');
    $source = new Table($sourcefile, null, $encoding);

    echo "Processing " . $source->getRecordCount() . " records from file $sourcefile to $destinationfile using $encoding encoding\n";

    $tableName = basename(strtolower($source->getName()), ".dbf");
    $createString = "CREATE TABLE " . escName($tableName) . " (\n";
    foreach ($source->getColumns() as $column) {
        $type = mapTypeToSql($column->getType(), $column->getLength(), $column->getDecimalCount());
        if (($column->getType() == Record::DBFFIELD_TYPE_MEMO)
            || ($column->getName() == "_nullflags")
            || ($type === false)) {
            continue;
        }
        $createString .= "\t" . escName($column->getName()) . " $type,\n";
    }
    $createString = substr($createString, 0, -2) . "\n) CHARACTER SET utf8 COLLATE utf8_unicode_ci;\n\n";
    fwrite($destination, $createString);

    $rows = 0;
    while ($record = $source->nextRecord()) {
        if ($record->isDeleted()) {
            continue;
        }
        if ($rows == 0) {
            $insertLine = "INSERT INTO " . escName($tableName) . " VALUES \n";
        } else {
            $insertLine .= ",\n";
        }
        $row = "\t(";
        foreach ($source->getColumns() as $column) {
            $type = mapTypeToSql($column->getType(), $column->getLength(), $column->getDecimalCount());
            if (($column->getType() == Record::DBFFIELD_TYPE_MEMO)
                || ($column->getName() == "_nullflags")
                || ($type === false)) {
                continue;
            }
            $cell = $record->getObject($column);
            if (($column->getType() == Record::DBFFIELD_TYPE_DATETIME) && $cell) {
                $cell = date('Y-m-d H:i:s', $cell-3600);
            }
            $row .= escData($cell, $column->getType()) . ",";
        }
        $row = substr($row, 0, -1) . ")";
        $insertLine .= $row;
        if ($rows + 1 == $batchSize) {
            $insertLine .= ";\n\n";
            $rows = 0;
            fwrite($destination, $insertLine);
            $insertLine = "";
        } else {
            $rows++;
        }
    }
    if (!empty($insertLine)) {
        $insertLine .= ";\n\n";
        fwrite($destination, $insertLine);
    }
    fclose($destination);
    echo "Export done: " . $source->getDeleteCount() . " deleted records ommitted\n";
}

function mapTypeToSql($type_short, $length, $decimal)
{
    $types = [
        Record::DBFFIELD_TYPE_MEMO => "TEXT",                        // Memo type field
        Record::DBFFIELD_TYPE_CHAR => "VARCHAR($length)",            // Character field
        Record::DBFFIELD_TYPE_DOUBLE => "DOUBLE($length,$decimal)",  // Double
        Record::DBFFIELD_TYPE_NUMERIC => "INTEGER",                  // Numeric
        Record::DBFFIELD_TYPE_FLOATING => "FLOAT($length,$decimal)", // Floating point
        Record::DBFFIELD_TYPE_DATE => "DATE",                        // Date
        Record::DBFFIELD_TYPE_LOGICAL => "TINYINT(1)",               // Logical - ? Y y N n T t F f (? when not initialized).
        Record::DBFFIELD_TYPE_DATETIME => "DATETIME",                // DateTime
        Record::DBFFIELD_TYPE_INDEX => "INTEGER",                    // Index
    ];

    if (array_key_exists($type_short, $types)) {
        return $types[$type_short];
    }
    return false;
}

function escName($name)
{
    return "`" . $name . "`";
}

function escData($data, $type)
{
    $escapedData = addslashes($data);
    if (in_array($type, array(
            Record::DBFFIELD_TYPE_MEMO,
            Record::DBFFIELD_TYPE_CHAR,
            Record::DBFFIELD_TYPE_DATE,
            Record::DBFFIELD_TYPE_DATETIME
        ))) {
        $escapedData = "\"" . $escapedData . "\"";
    }
    return $escapedData;
}
