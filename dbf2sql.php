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
    echo "Default batch size id 1000 rows inserted at once.\n\n";
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
    $pathInfo = pathinfo($sourcefile);
    $destinationfile = ($destDir ? $destDir : $pathInfo['dirname']) . "/" . $pathInfo['filename'] . ".sql";
    $destination = fopen($destinationfile, 'w');
    $source = new Table($sourcefile, null, $encoding);

    echo "Processing " . $source->getRecordCount() . " records from file $sourcefile to $destinationfile using $encoding encoding\n";

    $tableName = basename(strtolower($source->getName()), ".dbf");
    $createString = "CREATE TABLE " . escName($tableName) . " (\n";
    foreach ($source->getColumns() as $column) {
        if (($column->getType() == Record::DBFFIELD_TYPE_MEMO) || ($column->getName() == "_nullflags")) {
            continue;
        }
        $createString .= "\t" . escName($column->getName()) . " ";
        $createString .= mapTypeToSql($column->getType(), $column->getLength(), $column->getDecimalCount());
        $createString .= ",\n";
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
            if (($column->getType() == Record::DBFFIELD_TYPE_MEMO) || ($column->getName() == "_nullflags")) {
                continue;
            }
            $cell = $record->getObject($column);
            if (($column->getType() == Record::DBFFIELD_TYPE_DATETIME) && $cell) {
                $cell = date('Y-m-d H:i:s', $cell-3600);
            }
            $row .= "\"" . addslashes($cell) . "\",";
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
    switch ($type_short) {
        case Record::DBFFIELD_TYPE_MEMO: return "TEXT";                        // Memo type field
        case Record::DBFFIELD_TYPE_CHAR: return "VARCHAR($length)";            // Character field
        case Record::DBFFIELD_TYPE_DOUBLE: return "DOUBLE($length,$decimal)";  // Double
        case Record::DBFFIELD_TYPE_NUMERIC: return "INTEGER";                  // Numeric
        case Record::DBFFIELD_TYPE_FLOATING: return "FLOAT($length,$decimal)"; // Floating point
        case Record::DBFFIELD_TYPE_DATE: return "DATE";                        // Date
        case Record::DBFFIELD_TYPE_LOGICAL: return "TINYINT(1)";               // Logical - ? Y y N n T t F f (? when not initialized).
        case Record::DBFFIELD_TYPE_DATETIME: return "DATETIME";                // DateTime
        case Record::DBFFIELD_TYPE_INDEX: return "INTEGER";                    // Index
   }
}

function escName($name)
{
    return "`" . $name . "`";
}
