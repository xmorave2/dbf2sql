# dbf2sql

[![Codacy Badge](https://api.codacy.com/project/badge/Grade/8d8207c70a7040879f6f6853cb4f6f0f)](https://www.codacy.com/app/xmorave2/dbf2sql?utm_source=github.com&utm_medium=referral&utm_content=xmorave2/dbf2sql&utm_campaign=badger)
[![Dependency Status](https://www.versioneye.com/user/projects/58b74d1a9fd69a003e8d2c5a/badge.svg?style=flat-square)](https://www.versioneye.com/user/projects/58b74d1a9fd69a003e8d2c5a)

DBF 2 SQL is small command line tool for converting DBF files top MySQL dump format.

# Installation

1. Clone repository: <code>git clone https://github.com/xmorave2/dbf2sql.git</code>

2. Install php and composer (https://getcomposer.org/doc/00-intro.md#installation-linux-unix-osx)

3. Install libraries: <code>composer install</code>

# Use

<code>php dbf2sql.php [-e encoding] [-b batchsize] [-d destinationdir] list_of_dbf_files</code>

Example: 

<code>php dbf2sql.php -e CP1250 books.dbf authors.dbf</code>

