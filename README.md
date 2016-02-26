# dbf2sql
DBF 2 SQL is small command line tool for converting DBF files top MySQL dump format.

# Installation

1. Install php and composer

2. Clone repository:

<code>git clone https://github.com/xmorave2/dbf2sql.git</code>

3. Install libraries:

<code>composer install</code>

# Use

<code>php dbf2sql [-e encoding] list_of_dbf_files</code>

Example: 

<code>php dbf2sql.php -e CP1250 books.dbf authors.dbf</code>

