<?php

/**
* Project:  PhpZipUtils: A native PHP class for reading and writing zip archives
* File:     ZipFile.php
* Note:     Modified for Securimage-WP (removed namespaces for maximum
*           compatibility, merge all classes into single file)
*
* @link http://github.com/dapphp/PhpZipUtils PhpZipUtils on Github
* @author Drew Phillips <drew@drew-phillips.com>
* @version 1.5 (February 17, 2014)
* @package PhpZipUtils
*
*  **TODO:**
*   - Get WinZip AES decryption working
*   - Check for ZIP64 extensions
*   - Store path to file rather than reading data to memory when calling addFile()
*/

class Dapphp_PhpZipUtils_ZipFileEntry {
    /**
     * The full path and name of the entry in the archive
     *
     * @var string
     */
    public $filename;


    /**
     * The stored CRC32 from the archive in hex format
     *
     * @var string
     */
    public $crc;

    /**
     * The uncompressed size of the file
     *
     * @var int
     */
    public $size;

    /**
     * The compressed size of the file
     *
     * @var int
     */
    public $csize;

    /**
     * Any error encountered while processing the file
     *
     * @var int
     */
    public $error;

    /**
     * Description of the file error code
     *
     * @var string
     */
    public $errorMessage;

    /**
     * The decompressed file data
     *
     * @var string
     */
    public $data;

    /**
     * The name of the file in the archive (minus path)
     *
     * @var string
     */
    public $name;

    /**
     * The path of the file in the archive
     *
     * @var string
     */
    public $path;

    /**
     * Is the entry a directory, or a file
     */
    public $is_directory;

    /**
     * Unix timestamp of file modification
     *
     * @var int
     */
    public $timestamp;

    /**
     * DOS file modification date
     *
     * @var int
     */
    public $date;

    /**
     * DOS file modification time
     *
     * @var int
     */
    public $time;

    /**
     * File comment
     *
     * @var string
     */
    public $comment;

    public function __construct()
    {
        $this->filename = '';
        $this->comment = null;
        $this->crc = '';
        $this->size = 0;
        $this->csize = 0;
        $this->compression_ratio = 0;
        $this->error = Dapphp_PhpZipUtils_ZipFile::ER_OK;
        $this->errorMessage = '';
        $this->data = '';
        $this->name = '';
        $this->path = '';
        $this->is_directory = false;
        $this->date = 0;
        $this->time = 0;
        $this->timestamp = 0;
    }

    public function getCompressionRatio()
    {
        if ($this->size == 0) {
            return 0;
        } else {
            return round(100 - (($this->csize / $this->size) * 100), 0);
        }
    }
}

/*--*/

class Dapphp_PhpZipUtils_ZipDirectoryEntry extends Dapphp_PhpZipUtils_ZipFileEntry
{
    const ZIP_ST_UNCHANGED = 0;
    const ZIP_ST_DELETED   = 1;
    const ZIP_ST_REPLACED  = 2;
    const ZIP_ST_ADDED     = 3;
    const ZIP_ST_RENAMED   = 4;

    public $versionMadeBy;
    public $versionNeeded;
    public $bitFlags;
    public $compressionMethod;
    public $lastMod;

    public $filenameLength;

    public $commentLength;

    public $diskNumber;

    public $internalAttributes;

    public $externalAttributes;

    public $offset;

    public $extraFieldLength;

    public $extraFieldData;

    public $state;

    public $cd_offset; // central directory offset

    public function __construct()
    {
        $this->versionMadeBy = 20;
        $this->versionNeeded = 20;
        $this->state = self::ZIP_ST_UNCHANGED;

        parent::__construct();
    }

    public function isEncrypted()
    {
        return ($this->bitFlags & Dapphp_PhpZipUtils_ZipFile::ZIP_GPBF_ENCRYPTED) > 0;
    }

    public function toZipFileEntry()
    {
        $e = new Dapphp_PhpZipUtils_ZipFileEntry();

        foreach($e as $prop => $val) {
            $e->$prop = $this->$prop;
        }

        return $e;
    }
}

/*--*/

class Dapphp_PhpZipUtils_ZipCentralDirectory
{
    /**
     * Directory entries
     *
     * @var ZipDirectoryEntry
     */
    private $entry;

    /**
     * Number of entries
     *
     * @var int
     */
    private $numEntries;

    /**
     * Size of central directory
     *
     * @var int
     */
    private $size;

    /**
     * Offset of central directory
     *
     * @var int
     */
    private $offset;

    /**
     * Zip archive comment
     *
     * @var string
     */
    private $comment;

    public function __construct()
    {
        $this->entry   = array();
        $this->offset  = 0;
        $this->comment = '';
        $this->numEntries = 0;
    }

    public function addEntry($index, Dapphp_PhpZipUtils_ZipDirectoryEntry $entry)
    {
        $this->entry[$index] = $entry;
        $this->numEntries++;
        return $this;
    }

    public function getEntryIndex($index)
    {
        if (isset($this->entry[$index])) {
            return $this->entry[$index];
        } else {
            return false;
        }
    }

    public function setNentry($nentry)
    {
        $this->numEntries = $nentry;
        return $this;
    }

    public function getNentry()
    {
        return $this->numEntries;
    }

    public function setSize($size)
    {
        $this->size = $size;
        return $this;
    }

    public function getSize()
    {
        return $this->size;
    }

    public function setOffset($offset)
    {
        $this->offset = $offset;
        return $this;
    }

    public function getOffset()
    {
        return $this->offset;
    }

    public function setComment($comment)
    {
        $this->comment = $comment;
    }

    public function getComment()
    {
        return $this->comment;
    }
}

/*--*/

class Dapphp_PhpZipUtils_ZipFileIterator implements Iterator
{
    private $zipfile;
    private $centraldirectory;
    private $position;

    public function __construct(Dapphp_PhpZipUtils_ZipFile $zip, Dapphp_PhpZipUtils_ZipCentralDirectory $cd)
    {
        $this->zipfile = $zip;
        $this->centraldirectory = $cd;
        $this->position = 0;
    }

    function rewind()
    {
        $this->position = 0;
    }

    function current()
    {
        $cdent = $this->centraldirectory->getEntryIndex($this->position);

        /* @var $file ZipDirectoryEntry */
        $file = $this->zipfile->getFromIndex($this->position);
        $file->comment = $cdent->comment;

        return $file;
    }

    function key()
    {
        return $this->position;
    }

    function next()
    {
        ++$this->position;
    }

    function valid()
    {
        return ($this->centraldirectory->getEntryIndex($this->position) !== false);
    }
}

/*--*/

class Dapphp_PhpZipUtils_ZipFile
{
    // Mode constants - from PHP ZipArchive class
    const CREATE    = 1;
    const EXCL      = 2;
    const CHECKCONS = 4;
    const OVERWRITE = 8;

    // Compression method constants - from PHP ZipArchive class
    const CM_STORE   = 0;
    const CM_DEFLATE = 8;
    const CM_BZIP2   = 12;

    // Error constants - from PHP ZipArchive Class
    const ER_OK  = 0;
    const ER_MULTIDISK = 1;
    const ER_CLOSE = 3;
    const ER_SEEK = 4;
    const ER_READ = 5;
    const ER_WRITE = 6;     // not used
    const ER_CRC = 7;
    const ER_ZIPCLOSED = 8; // not used
    const ER_NOENT = 9;
    const ER_EXISTS = 10;
    const ER_OPEN = 11;
    const ER_TMPOPEN = 12;
    const ER_ZLIB = 13;
    const ER_MEMORY = 14;   // not used
    const ER_CHANGED = 15;  // not used
    const ER_COMPNOTSUPP = 16;
    const ER_EOF = 17;
    const ER_INVAL = 18;    // not used
    const ER_NOZIP = 19;
    const ER_INTERNAL = 20; // not used
    const ER_INCONS = 21;
    const ER_REMOVE = 22;   // not used
    const ER_DELETED = 23;  // not used

    // Error constants - class specific
    const ER_ENCRYPTED  = 0x1001;
    const ER_BADPASS    = 0x1002;
    const ER_BZIP_ERROR = 0x1003;

    // String constants
    const CENTRAL_MAGIC = "PK\1\2";
    const LOCAL_MAGIC   = "PK\3\4";
    const EOCD_MAGIC    = "PK\5\6";
    const DATADESCRIPTOR_MAGIC = "PK\x7\x8";

    // General purpose bit flags
    const ZIP_GPBF_ENCRYPTED         = 0x0001;
    const ZIP_GPBF_DATA_DESCRIPTOR   = 0x0008;
    const ZIP_GPBF_STRONG_ENCRYPTION = 0x0040;

    // Size constants
    const CDENTRYSIZE   = 46; // central directory size
    const LENTRYSIZE    = 30; // local file entry size
    const ENCHEADERLEN  = 12; // size of the encryption header (pkzip)

    // Class properties
    private $_filename = '';
    private $_open = false;
    private $_readOnly;
    private $_files = array();
    private $_changed = false;
    private $_centralDirectory;
    private $_password;
    private $_error = self::ER_OK;
    private $_errorMessage = '';
    private $_overwriteExisting = false;
    private $_outputPath;
    private $_createdDirectories = array();
    private $_createdFiles = array();
    private $_fp;   // file pointer or byte offset of file
    private $_fileData;
    private $_fromFile;
    private $_state;
    private $_readBuffer;
    private $_readBufferSize = 0;

    /**
     * ZipFile constructor
     *
     * Example:
     *
     *     $zip = new PhpZipUtils\ZipFile();
     *     if ($zip->open('archive.zip')) {
     *         foreach($zip->getFiles() as $file) {
     *             echo "$file->filename - $file->size bytes<br />\n";
     *         }
     *     } else {
     *         echo "Failed to open zip file: " . $zip->getStatusString() . "\n";
     *     }
     */
    public function __construct()
    {
        $this->_init();
    }

    /**
     * Gets a 2-element array containing the current error code and error message of the ZipFile object
     *
     * @return array $array[0] = error code, $array[1] = error message
     */
    public function getErrorStatus()
    {
        return array($this->_error, $this->_errorMessage);
    }

    /**
     * Return the error code of the last error encountered.
     *
     * @return int The error code which is one of the ZipFile::ER_* class constants.
     */
    public function getStatusCode()
    {
        return $this->_error;
    }

    /**
     * Return a textual description of the last error encountered.
     *
     * @return string A message describing the error
     */
    public function getStatusString()
    {
        if ($this->_error != self::ER_OK) {
            return $this->_errorMessage;
        } else {
            return false;
        }
    }

    public function getReadBufferSize()
    {
        return $this->_readBufferSize;
    }

    public function setReadBufferSize($size)
    {
        $size = (int)$size;
        if ($size >= 0) {
            $this->_readBufferSize = $size;
        }
        return $this;
    }

    /**
     * After calling extractTo(), returns list of directories that were created
     *
     * @return array A list of created directories
     */
    public function getCreatedDirectories()
    {
        return $this->_createdDirectories;
    }

    /**
     * After calling extractTo(), returns a list of files that were created
     *
     * @return Array A list of created files.  Some files may not be created if
     *     setOverwriteExisting(false) is called and the file(s) already exist.
     */
    public function getCreatedFiles()
    {
        return $this->_createdFiles;
    }

    /**
     * Gets the archive comment (if any) of the currently opened zip archive.
     *
     * @see ZipFile::setArchiveComment() setArchiveComment()
     * @return string
     */
    public function getArchiveComment()
    {
        if ($this->_centralDirectory instanceof Dapphp_PhpZipUtils_ZipCentralDirectory) {
            return $this->_centralDirectory->getComment();
        } else {
            return null;
        }
    }

    /**
     * Sets the comment data for the current archive
     *
     * @param string $comment The comment add to the archive
     * @return bool false if the archive is read-only, true otherwise
     */
    public function setArchiveComment($comment)
    {
        if ($this->_readOnly) {
            return false;
        } else if (!($this->_centralDirectory instanceof Dapphp_PhpZipUtils_ZipCentralDirectory)) {
            return false;
        } else {
            $this->_centralDirectory->setComment($comment);
            $this->_changed = true;
            return true;
        }
    }

    /**
     * Sets a flag indicating if existing files should be overwritten when extracting archives to disk
     *
     * @param bool $overwrite true if existing files should be overwritten, false to leave existing files as-is.
     * @return ZipFile The current object
     */
    public function setOverwriteExisting($overwrite)
    {
        $this->_overwriteExisting = (bool)$overwrite;
        return $this;
    }

    /**
     * Returns the value of the flag indicating if existing files are overwritten when extracting to disk
     *
     * @return bool true if archives will be overwritten, false otherwise.
     */
    public function getOverwriteExisting()
    {
        return $this->_overwriteExisting;
    }

    /**
     * Set the password to use for encrypting/decrypting the archive.
     *
     * File data will be encrypted using this password. Currently only PKZip encryption is supported.
     *
     * @param string $password The password to use for encrypting/decrypting
     * @return ZipFile The current object
     */
    public function setArchivePassword($password)
    {
        $this->_password = $password;
        return $this;
    }

    /**
     * Get the password set by setArchivePassword
     *
     * @see ZipFile::setArchivePassword() setArchivePassword()
     * @return string The password
     */
    public function getArchivePassword()
    {
        return $this->_password;
    }

    /**
     * Open an archive for reading, writing, or creation.
     *
     * Examples:
     *
     *     // open an existing file for reading
     *     $opened = $zip->open('file.zip');
     *
     *     // create a new, empty zip archive
     *     $opened = $zip->open('new.zip', ZipFile::CREATE);
     *
     *     // overwrite an existing archive, fails if file does not exist
     *     $opened = $zip->open('exists.zip', ZipFile::OVERWRITE);
     *
     *     // create a new archive, fails if it already exists
     *     $opened = $zip->open('file.zip', ZipFile::CREATE | ZipFile::EXCL);
     *
     * @param string $zipfile  The path to the file to read or write
     * @param int $flags       Open flags.  A bitwise combination of ZipFile::CREATE, ZipFile::EXCL and ZipFile::OVERWRITE.
     * @return bool            true if the file was opened successfully, false otherwise
     */
    public function open($zipfile, $flags = 0)
    {
        $this->_init();
        $this->_readOnly = true;
        $this->_fromFile = true;
        $this->_files    = array();
        $exists          = file_exists($zipfile);

        if (($flags & self::OVERWRITE) > 0 && !$exists) {
            return $this->_setError(self::ER_NOENT, 'No such file');
        } else if ($exists && ($flags & self::EXCL) > 0) {
            return $this->_setError(self::ER_EXISTS, 'File already exists');
        } else if (($flags & self::OVERWRITE) && $exists) {
            $mode = 'a+b';
            $this->_readOnly = false;
        } else if (($flags & self::CREATE) > 0 || ($flags & self::OVERWRITE) > 0) {
            $mode = 'w+b';
            $this->_readOnly = false;
        } else {
            $mode = 'rb';
        }

        if ($exists) {
            $zipfile = realpath($zipfile);
        }

        $this->_fp = fopen($zipfile, $mode);
        if (!$this->_fp) {
            $this->_readOnly = true;
            return $this->_setError(self::ER_OPEN, 'Failed to open file');
        }

        if (!$exists) {
            $zipfile = realpath($zipfile);
        }

        if (!($cd = $this->findCentralDirectory())) {
            if (!$this->_readOnly) {
                $cd = new Dapphp_PhpZipUtils_ZipCentralDirectory();
            } else {
                return false;
            }
        }

        $this->_centralDirectory = $cd;
        $this->_open             = true;
        $this->_filename         = $zipfile;

        return true;
    }

    /**
     * Open a zip archive from memory
     *
     * @param string $zipdata The data representing a complete, valid zip archive
     * @return bool  true if opened, false if not a zip file or format error
     */
    public function openFromString($zipdata)
    {
        $this->_init();
        $this->_fileData = $zipdata;
        $this->_fromFile = false;
        $this->_fp       = 0;
        $this->_readOnly = true;
        $this->_files    = array();

        if (!($cd = $this->findCentralDirectory())) {
            return false;
        }

        $this->_centralDirectory = $cd;
        $this->_open             = true;

        return true;
    }

    /**
     * Extract all files from the currently opened file to disk
     *
     * @param string $destination  The location to extract to.  Does not need to exist, but must be writeable.
     * @return bool  true if the files were extracted, false if errors occurred
     */
    public function extractTo($destination)
    {
        $this->_createdDirectories = array();
        $this->_createdFiles       = array();
        $this->_outputPath         = $destination;
        $cd                        = $this->_centralDirectory;

        for ($i = 0; $i < $cd->getNentry(); $i++) {
            /* @var $cdent Dapphp_PhpZipUtils_ZipDirectoryEntry */
            $cdent = $cd->getEntryIndex($i);

            /* @var $file Dapphp_PhpZipUtils_ZipDirectoryEntry */
            $file = $this->getFromIndex($i);
            if (!$file) {
                return false;
            }

            if ($file->error == self::ER_OK) {
                $this->_writeDataToFile($file);
            }

            $file->data = null;
        }

        return true;
    }

    /**
     * Returns an iterator containing the files in the archive.
     *
     * @return Dapphp_PhpZipUtils_ZipFileIterator The file iterator
     */
    public function getFiles()
    {
        return new Dapphp_PhpZipUtils_ZipFileIterator($this, $this->getCentralDirectory());
    }

    /**
     * Add an empty directory to the archive
     *
     * @param string $dirname Directory path to add
     * @return bool true if added, false if error
     */
    public function addEmptyDir($dirname)
    {
        if ($this->_readOnly) {
            return false;
        }

        if (empty($dirname)) {
            return false;
        }

        if (substr($dirname, -1) != '/') {
            $dirname .= '/';
        }

        if ( ($i = $this->locateName($dirname)) >= 0) {
            $this->_files[$i]->filename = $dirname;
            $this->_files[$i]->filenameLength = strlen($dirname);
            $this->_files[$i]->state = Dapphp_PhpZipUtils_ZipDirectoryEntry::ZIP_ST_RENAMED;
        } else {
            $file = new Dapphp_PhpZipUtils_ZipDirectoryEntry();
            $file->filename = $dirname;
            $file->filenameLength = strlen($dirname);
            $file->state = Dapphp_PhpZipUtils_ZipDirectoryEntry::ZIP_ST_ADDED;
            $this->_files[] = $file;
        }

        $this->_changed = true;

        return true;
    }

    /**
     * Add a file to the current archive. Files in the archive with the same name will be replaced
     *
     * @param string $filename The path to the file on disk to add
     * @param mixed $localname The name of the file within the archive, or null to use the name from disk
     * @return bool true if the file was successfully added, false if an error occurs
     */
    public function addFile($filename, $localname = null)
    {
        if ($this->_readOnly) {
            return false;
        }

        if (!file_exists($filename) || !is_readable($filename)) {
            return false;
        }

        if (is_null($localname)) {
            $localname = $filename;
        }

        if ($localname{0} == '/' || $localname{0} == '\\') {
            $localname = substr($localname, 1);
        }

        $idx = $this->locateName($localname);
        if ($idx >= 0) {
            $this->deleteIndex($idx);
        }

        $file        = new Dapphp_PhpZipUtils_ZipDirectoryEntry();
        $file->data  = file_get_contents($filename);
        $file->crc   = crc32($file->data);
        $file->size  = strlen($file->data);
        $file->filename = $localname;
        $file->filenameLength = strlen($localname);
        $file->name  = basename($localname);
        $file->path  = dirname($localname);
        $file->compressionMethod = self::CM_DEFLATE;
        $file->bitFlags = 0;
        $file->state = $file::ZIP_ST_ADDED;
        $file->extraFieldData = '';
        $file->extraFieldLength = 0;

        $this->_changed = true;
        $this->_files[] = $file;

        return true;
    }

    /**
     * Add a file to the archive using a string as the file content
     *
     * @param string $localname  The name of the file to add
     * @param unknown $contents  The contents of the file to add
     * @return bool  true if added, false if error
     */
    public function addFromString($localname, $contents)
    {
        if ($this->_readOnly) {
            return false;
        }

        if ($localname{0} == '/' || $localname{0} == '\\') {
            $localname = substr($localname, 1);
        }

        if (substr($localname, -1) == '/') {
            $localname = substr($localname, 0, -1);
        }

        if (empty($localname)) {
            return false;
        }

        $idx = $this->locateName($localname);
        if ($idx >= 0) {
            $this->deleteIndex($idx);
        }

        $file        = new Dapphp_PhpZipUtils_ZipDirectoryEntry();
        $file->data  = $contents;
        $file->crc   = crc32($file->data);
        $file->size  = strlen($file->data);
        $file->filename = $localname;
        $file->filenameLength = strlen($localname);
        $file->name  = basename($localname);
        $file->path  = dirname($localname);
        $file->compressionMethod = self::CM_DEFLATE;
        $file->bitFlags = 0;
        $file->state = $file::ZIP_ST_ADDED;
        $file->extraFieldData = '';
        $file->extraFieldLength = 0;

        $this->_changed = true;
        $this->_files[] = $file;

        return true;
    }

    /**
     * Delete a file from the archive by its name
     *
     * @param string $name The name of the file within the archive
     * @return bool  true if removed, false if an error occurs
     */
    public function deleteName($name)
    {
        if ($this->_readOnly) {
            return false;
        }

        $return = false;
        $dir    = (substr($name, -1)) == '/';

        foreach($this->_files as $idx => $file) {
            if ($dir && strpos($file->filename, $name) === 0) {
                $this->_files[$idx]->state = Dapphp_PhpZipUtils_ZipDirectoryEntry::ZIP_ST_DELETED;
                $return = true;
            } else if ($name === $file->filename) {
                $this->_files[$idx]->state = Dapphp_PhpZipUtils_ZipDirectoryEntry::ZIP_ST_DELETED;
                $return = true;
            }
        }

        if ($return) $this->_changed = true;

        return $return;
    }

    /**
     * Remove a file from the archive by its numeric index
     *
     * @param int $index The index of the file
     * @see ZipFile::locateName() ZipFile::locateName() - gets the index of a file by its name
     * @return bool true if removed, false if index doesn't exist or archive is read only
     */
    public function deleteIndex($index)
    {
        if ($this->_readOnly) {
            return false;
        }

        if (!isset($this->_files[$index])) {
            return false;
        }

        $this->_files[$index]->state = Dapphp_PhpZipUtils_ZipDirectoryEntry::ZIP_ST_DELETED;
        $this->_changed = true;

        return true;
    }

    /**
     * Get a reference to a ZipFileEntry based on its index in the archive
     *
     * @param int $index  The index of the file to return
     * @return mixed false if not found or couldn't be read, otherwise returns a ZipFileEntry object
     */
    public function getFromIndex($index)
    {
        if (isset($this->_files[$index])) {
            $file = clone $this->_files[$index];
            if (!$file->is_directory) {
                $this->readFileData($this->_centralDirectory->getEntryIndex($index), $file);
            }
            return $file->toZipFileEntry();
        } else {
            return false;
        }
    }

    /**
     * Returns the index of the entry in the archive
     *
     * @param string $name The name of the entry to find
     * @return int -1 if not found, otherwise the numeric index of the file
     */
    public function locateName($name)
    {
        for ($i = 0; $i < sizeof($this->_files); ++$i) {
            /* @var $entry Dapphp_PhpZipUtils_ZipDirectoryEntry */
            $entry = $this->_files[$i];
            if ($entry->state == Dapphp_PhpZipUtils_ZipDirectoryEntry::ZIP_ST_DELETED) continue;

            if ($entry->filename == $name) {
                return $i;
            }
        }

        return -1;
    }

    /**
     * Sets the comment for a file in the archive based on its numeric index
     *
     * @param int $index The index of the file to add a comment for
     * @param string $comment The comment to add
     * @return mixed true if comment set, false otherwise
     */
    public function setCommentIndex($index, $comment)
    {
        if (empty($comment)) {
            return false;
        }

        if (isset($this->_files[$index]) && Dapphp_PhpZipUtils_ZipDirectoryEntry::ZIP_ST_DELETED != $this->_files[$index]->state) {
            $this->_files[$index]->comment = $comment;
            $this->_files[$index]->commentLength = strlen($comment);
            $this->_changed = true;
            return true;
        }

        return false;
    }

    /**
     * Sets the comment for a file in the archive based on its name
     *
     * @param string $name The name of the file in the archive to add comment to
     * @param string $comment The comment to add
     * @return mixed false if name not found or comment empty, true otherwise
     */
    public function setCommentName($name, $comment)
    {
        if ( ($idx = $this->locateName($name)) >= 0) {
            return $this->setCommentIndex($idx, $comment);
        } else {
            return false;
        }
    }

    /**
     * Initializes the ZipFile object to its default (invalid) state
     *
     * @return void
     */
    private function _init()
    {
        if ($this->_open && $this->_fromFile) {
            @fclose($this->_fp);
        }

        $this->_filename           = null;
        $this->_open               = false;
        $this->_readOnly           = true;
        $this->_files              = array();
        $this->_centralDirectory   = new Dapphp_PhpZipUtils_ZipCentralDirectory();
        $this->_changed            = false;
        $this->_error              = self::ER_OK;
        $this->_errorMessage       = null;
        $this->_fp                 = null;
        $this->_fileData           = null;
        $this->_readBuffer         = '';
        $this->_createdDirectories = array();
        $this->_createdFiles       = array();
        $this->_overwriteExisting  = false;
    }

    private function getCentralDirectory()
    {
        return $this->_centralDirectory;
    }

    private function readFileData(Dapphp_PhpZipUtils_ZipDirectoryEntry $cd, Dapphp_PhpZipUtils_ZipDirectoryEntry $file)
    {
        if ($file->csize == 0) {
            $file->data = '';
            return true;
        }

        if (!$this->fseek($cd->offset + self::LENTRYSIZE + $file->filenameLength + $file->extraFieldLength, SEEK_SET)) {
            return $this->_setError(self::ER_SEEK, 'Failed to see to beginning of file data');
        }

        $file->data = $this->_readFromStream($file->csize);

        if (!$this->_checkEnd($file->data, $file->csize)) {
            return false;
        }

        if ($file->isEncrypted()) {
            // file is encrypted
            if ($file->compressionMethod == 99) {
                return $this->_setError(self::ER_ENCRYPTED, 'File is AES encrypted, but AES is not supported');
            }

            if (!$this->decryptFileData($file)) {
                $file->error = $this->_error;
                $file->errorMessage = $this->_errorMessage;
                return false;
            }
        }

        if (!$this->decompressData($file)) {
            $file->error = $this->_error;
            $file->errorMessage = $this->_errorMessage;
            return false;
        }

        return true;
    }

    private function decryptFileData(Dapphp_PhpZipUtils_ZipDirectoryEntry $e)
    {
        // Removed for Securimage-WP
    }

    private function encryptFileData(&$data, Dapphp_PhpZipUtils_ZipDirectoryEntry $e)
    {
        // Removed for Securimage-WP
    }

    private function decompressData(Dapphp_PhpZipUtils_ZipDirectoryEntry $e)
    {
        switch ($e->compressionMethod) {
            case self::CM_STORE:
                // no-op - data is not compressed
                break;

            case self::CM_DEFLATE:
                if (function_exists('gzinflate')) {
                    $e->data = gzinflate($e->data);
                    if ($e->data === false) {
                        $e->data = null;
                        $e->error = self::ER_ZLIB;
                        $e->errorMessage = 'gzinflate() failed';
                        return false;
                    }
                } else {
                    $e->error = self::ER_COMPNOTSUPP;
                    $e->errorMessage = 'No zlib support, cannot uncompress';
                    return false;
                }
                break;

            case self::CM_BZIP2:
                if (function_exists('bzdecompress')) {
                    $e->data = bzdecompress($e->data);
                    if (is_int($e->data)) {
                        // decompress failed
                        $e->error = self::ER_BZIP_ERROR;
                        $e->errorMessage = 'bzdecompress failed with error ' . $e->data;
                        return false;
                    }
                } else {
                    $e->error = self::ER_COMPNOTSUPP;
                    $e->errorMessage = 'No bzip support, cannot uncompress';
                    return false;
                }
                break;

            default:
                $e->error = self::ER_COMPNOTSUPP;
                $e->errorMessage = 'Compression method ' . $e->compressionMethod . ' not supported';
                return false;
                break;
        }

        if (crc32($e->data) != $e->crc) {
            $e->error = self::ER_CRC;
            $e->errorMessage = 'CRC32 of file data does not match stored CRC value';
        }

        return true;
    }

    private function reset()
    {
        if ($this->_fromFile) {
            fseek($this->_fp, 0, SEEK_SET);
        } else {
            $this->_fp = 0;
        }
    }

    private function findCentralDirectory()
    {
        if (!$this->fseek(0, SEEK_END)) {
            return $this->_setError(self::ER_SEEK, 'Failed to seek to end of file');
        }

        $len = $this->ftello();
        $i   = $this->fseek(-($len < 65535 + self::LENTRYSIZE ? $len : 65535 + self::LENTRYSIZE), SEEK_END);
        if (!$i) {
            return $this->_setError(self::ER_SEEK, 'Failed to seek before end of central directory entry');
        }

        $bufOffset = $this->ftello();
        $buf       = $this->_readFromStream(65535 + self::LENTRYSIZE);
        $bufLen    = strlen($buf);

        if (!$buf) {
            return $this->_setError(self::ER_READ, 'Error reading for end of central directory');
        }

        $best  = -1;
        $cdir  = null;
        $match = 0;
        $this->_setError(self::ER_NOZIP, 'Not a zip file');

        while (($match = strpos($buf, self::EOCD_MAGIC, $match)) !== false) {
            $match++;
            if (($cdirnew = $this->readCentralDirectory($bufOffset, $buf, $match-1)) === false)
                continue;

            if($cdir) {
                if ($best <= 0)
                    $best = $this->zipCheckConsistency($cdir);

                $a = $this->zipCheckConsistency($cdirnew);

                if ($best < $a) {
                    $cdir = $cdirnew;
                    $best = $a;
                } else {
                    $cdirnew = null;
                }
            } else {
                $cdir = $cdirnew;
                $best = $this->zipCheckConsistency($cdir);
            }
            $cdirnew = null;
        }

        if (!$best || $best < 0) {
            return false;
        }

        $this->_setError(self::ER_OK, '');

        return $cdir;
    }

    private function readCentralDirectory($bufOffset, $buf, $eocd)
    {
        $cd = new Dapphp_PhpZipUtils_ZipCentralDirectory();

        $comlen = strlen($buf) - $eocd - 22;
        if ($comlen < 0) {
            return $this->_setError(self::ER_NOZIP, 'Not enough bytes left for comment');
        }

        if (strpos($buf, self::EOCD_MAGIC, $eocd) - $eocd !== 0) {
            return $this->_setError(self::ER_NOZIP, 'Bad end of central directory magic');
        }

        if (strcmp(substr($buf, $eocd + 4, 4), "\0\0\0\0") !== 0) {
            return $this->_setError(self::ER_MULTIDISK, "Multi-disk archives not supported");
        }

        $cdp = $eocd + 8;

        // number of cdir entries on disk
        $i   = array_shift(unpack('v', substr($buf, $cdp, 2)));
        $cdp += 2;

        $cd->setNentry(array_shift(unpack('v', substr($buf, $cdp, 2)))); // number of cdir entries
        $cdp += 2;

        $cd->setSize(array_shift(unpack('V', substr($buf, $cdp, 4))));
        $cdp += 4;

        $cd->setOffset(array_shift(unpack('V', substr($buf, $cdp, 4))));
        $cdp += 4;

        $cd->setComment(null);
        $comlen2 = array_shift(unpack('v', substr($buf, $cdp, 2)));
        $cdp += 2;

        if ($cd->getOffset() + $cd->getSize() > $bufOffset + $cdp) {
            return $this->_setError(self::ER_INCONS, 'Central directory spans past EOCD record');
        }

        if ($comlen < $comlen2 || $cd->getNentry() != $i) {
            return $this->_setError(self::ER_NOZIP, 'Incorrect comment size or invalid number of entries');
        }

        // do comment length consistency check here

        if ($comlen2) {
            $cd->setComment(substr($buf, $cdp, $comlen2));
            $cdp += $comlen2;
        }

        if (!$this->fseek($cd->getOffset(), SEEK_SET)) {
            return $this->_setError(self::ER_SEEK, 'Failed to seek to start of central directory offset');
        }

        $left = $cd->getSize();
        $i    = 0;

        while ($i < $cd->getNentry() && $left > 0) {
            if (($dirent = $this->readZipDirectoryEntry($left, false)) === false) {
                return false;
            }

            $cd->addEntry($i, $dirent);
            $i++;
        }

        // TODO: read zip64 data here

        $cd->setNentry($i);

        return $cd;
    }

    private function readZipDirectoryEntry(&$left, $local)
    {
        $de = new Dapphp_PhpZipUtils_ZipDirectoryEntry();

        if ($local) {
            $size = self::LENTRYSIZE;
        } else {
            $size = self::CDENTRYSIZE;
        }

        if ($left && $left < $size) {
            return $this->_setError(self::ER_NOZIP, 'Not enough bytes left to read directory entry');
        }

        $cur = $this->_readFromStream($size);
        if (strlen($cur) < $size) {
            return $this->_setError(self::ER_NOZIP, 'Not enough bytes to read local file header');
        }

        if (strcmp(substr($cur, 0, 4), ($local ? self::LOCAL_MAGIC : self::CENTRAL_MAGIC)) !== 0) {
            return $this->_setError(self::ER_NOZIP, 'Invalid directory entry header');
        }

        $cur = substr($cur, 4);

        if (!$local) {
            $de->versionMadeBy = array_shift(unpack('v', substr($cur, 0, 2)));
            $cur = substr($cur, 2);
        } else {
            $de->versionMadeBy = 0;
        }

        $de->versionNeeded = array_shift(unpack('v', substr($cur, 0, 2)));
        $cur = substr($cur, 2);

        $de->bitFlags = array_shift(unpack('v', substr($cur, 0, 2)));
        $cur = substr($cur, 2);

        $de->compressionMethod = array_shift(unpack('v', substr($cur, 0, 2)));
        $cur = substr($cur, 2);

        $dostime = array_shift(unpack('v', substr($cur, 0, 2)));
        $cur = substr($cur, 2);
        $dosdate = array_shift(unpack('v', substr($cur, 0, 2)));
        $cur = substr($cur, 2);

        $de->date      = $dosdate;
        $de->time      = $dostime;
        $de->timestamp = mktime( ($dostime >> 11) & 0x0f,
                ($dostime >> 5)  & 0x1f,
                $dostime & 0x0f,
                ($dosdate >>  5) & 0x0f,
                $dosdate & 0x1f,
                (($dosdate >>  9) & 0x7f) + 1980 );

        $de->crc = array_shift(unpack('V', substr($cur, 0, 4)));
        $cur = substr($cur, 4);

        $de->csize = array_shift(unpack('V', substr($cur, 0, 4)));
        $cur = substr($cur, 4);

        $de->size = array_shift(unpack('V', substr($cur, 0, 4)));
        $cur = substr($cur, 4);

        $de->filenameLength = array_shift(unpack('v', substr($cur, 0, 2)));
        $cur = substr($cur, 2);

        $de->extraFieldLength = array_shift(unpack('v', substr($cur, 0, 2)));
        $cur = substr($cur, 2);

        if ($local) {
            $de->commentLength = 0;
            $de->diskNumber = 0;
            $de->internalAttributes = 0;
            $de->externalAttributes = 0;
            $de->offset = 0;
        } else {
            $de->commentLength = array_shift(unpack('v', substr($cur, 0, 2)));
            $de->diskNumber = array_shift(unpack('v', substr($cur, 2, 2)));
            $de->internalAttributes = array_shift(unpack('v', substr($cur, 4, 2)));
            $de->externalAttributes = array_shift(unpack('V', substr($cur, 6, 4)));
            $de->offset = array_shift(unpack('V', substr($cur, 10, 4)));
            $cur = substr($cur, 14);
        }

        $de->name = null;
        $de->extraFieldData = null;
        $de->comment = null;

        $datalen = $de->filenameLength + $de->extraFieldLength + $de->commentLength;
        $size   += $datalen;

        if ($left && $left < $size) {
            return $this->_setError(self::ER_NOZIP, 'Not enough data for file name and extra field length');
        }

        $cur = $this->_readFromStream($datalen);

        if ($de->filenameLength) {
            $de->filename = substr($cur, 0, $de->filenameLength);
            $de->name     = basename($de->filename);
            $de->path     = (dirname($de->filename) == '.' || dirname($de->filename) == '')
            ? '' : dirname($de->filename);
            $de->is_directory = (substr($de->filename, -1) == '/');

            $cur = substr($cur, $de->filenameLength);
        }

        if ($de->extraFieldLength) {
            $de->extraFieldData = substr($cur, 0, $de->extraFieldLength);
            $cur = substr($cur, $de->extraFieldLength);
        }

        if ($de->commentLength) {
            $de->comment = substr($cur, 0, $de->commentLength);
        }

        if ($left) {
            $left -= $size;
        }

        return $de;
    }

    private function zipCheckConsistency(Dapphp_PhpZipUtils_ZipCentralDirectory $cd)
    {
        if ($cd->getNentry()) {
            $max = $cd->getEntryIndex(0)->offset;
            $min = $cd->getEntryIndex(0)->offset;
        } else {
            $min = $max = 0;
        }

        for ($i = 0; $i < $cd->getNentry(); $i++) {
            if ($cd->getEntryIndex($i)->offset < $min) {
                $min = $cd->getEntryIndex($i)->offset;
            }
            if ($min > $cd->getOffset()) {
                return $this->_setError(self::ER_NOZIP, 'Local file offset is greater than central directory offset');
            }

            $j = $cd->getEntryIndex($i)->offset + $cd->getEntryIndex($i)->csize
            + strlen($cd->getEntryIndex($i)->filename) + self::LENTRYSIZE;

            if ($j > $max) {
                $max = $j;
            }
            if ($max > $cd->getOffset()) {
                return $this->_setError(self::ER_NOZIP, 'Local file entry extends past start of central directory');
            }

            if (!$this->fseek($cd->getEntryIndex($i)->offset, SEEK_SET)) {
                return $this->_setError(self::ER_SEEK, 'Failed to seek to local file entry offset');
            }

            if (($temp = $this->readZipDirectoryEntry($o = 0, true)) === false) {
                return false;
            }

            if (($cd->getEntryIndex($i)->bitFlags & self::ZIP_GPBF_DATA_DESCRIPTOR) > 0 && $temp->crc == 0) {
                if (!$this->readDataDescriptor($cd->getEntryIndex($i), $temp)) {
                    return false;
                }
            }

            if ($this->zipHeaderCompare($cd->getEntryIndex($i), 0, $temp, 1) != 0) {
                return $this->_setError(self::ER_INCONS, 'Local file header does not match central directory header');
            }

            $this->_files[$i] = $temp;
        }

        return $max - $min;
    }

    private function readDataDescriptor(Dapphp_PhpZipUtils_ZipDirectoryEntry $cde, Dapphp_PhpZipUtils_ZipDirectoryEntry &$local)
    {
        if (!$this->fseek($cde->offset + self::LENTRYSIZE + $cde->filenameLength + $local->extraFieldLength + $cde->csize, SEEK_SET)) {
            return $this->_setError(self::ER_SEEK, 'Failed to seek to local file data descriptor');
        }

        $temp = $this->_readFromStream(4);

        if (strcmp($temp, self::DATADESCRIPTOR_MAGIC) == 0) {
            $crc = array_shift(unpack('V', $this->_readFromStream(4)));
        } else {
            $crc = array_shift(unpack('V', $temp));
        }

        $local->crc = $crc;
        $data       = $this->_readFromStream(8);
        list($local->csize, $local->size) = array_values(unpack('Vcsize/Vsize', $data));

        return true;
    }

    private function zipHeaderCompare(Dapphp_PhpZipUtils_ZipDirectoryEntry $h1, $local1, Dapphp_PhpZipUtils_ZipDirectoryEntry $h2, $local2)
    {
        if (($h1->versionNeeded != $h2->versionNeeded)
                ||  ($h1->compressionMethod != $h2->compressionMethod)
                ||  ($h1->lastMod != $h2->lastMod)
                ||  ($h1->filename != $h2->filename)) {
                    return -1;
                }

                if (($h1->crc != $h2->crc) || ($h1->csize != $h2->csize) || ($h1->size != $h2->size)) {
                    return -1;
                }

                return 0;
    }

    public function close()
    {
        if (!$this->_readOnly && $this->_changed) {
            $temp = tmpfile();
            if (!$temp) {
                return $this->_setError(self::ER_TMPOPEN, 'Failed to create temporary file for zip output');
            }

            $cd       = new Dapphp_PhpZipUtils_ZipCentralDirectory();
            $filelist = array();

            /* @var $file ZipFileEntryInternal */
            foreach($this->_files as $index => $file) {
                if ($file->state == Dapphp_PhpZipUtils_ZipDirectoryEntry::ZIP_ST_DELETED)
                    continue;

                $filelist[$index] = $file;
            }

            uasort($filelist, function($a, $b) {
                return strcmp($a->filename, $b->filename);
            });

                /* @var $file Dapphp_PhpZipUtils_ZipDirectoryEntry */
                $i      = 0;
                $offset = 0;
                foreach($filelist as $index => $file) {
                    $file->offset = $offset;
                    $cd->addEntry($i, $file);

                    if (!($entry = $this->_centralDirectory->getEntryIndex($index))) {
                        $entry = null;
                    }

                    $this->zipWriteLocalEntry($temp, $file, $entry);
                    $i++;
                    $offset = ftell($temp);
                }

                $cd_offset = ftell($temp);
                $cd->setOffset($cd_offset);

                $cd_size = $this->zipWriteCentralDirectory($temp, $cd);

                // end of central directory
                fwrite($temp, "\x50\x4b\x05\x06"); // end of cd header
                fwrite($temp, pack('v', 0));       // # of this disk (always 0)
                fwrite($temp, pack('v', 0));       // # of disk w/ start of cd
                fwrite($temp, pack('v', $i));       // # of entries in cd on this disk
                fwrite($temp, pack('v', $i));      // total # of entries in cd
                fwrite($temp, pack('V', $cd_size));       // size of the cd
                fwrite($temp, pack('V', $cd_offset));       // offset of start of cd
                fwrite($temp, pack('v',
                        strlen($this->getArchiveComment())));     // .zip file comment length

                if (strlen($this->getArchiveComment())) {
                    fwrite($temp, $this->getArchiveComment());
                }

                ftruncate($this->_fp, 0);
                fseek($temp, 0, SEEK_SET);

                while (!feof($temp)) {
                    fwrite($this->_fp, fread($temp, 0x8000));
                }

                fclose($temp);
        }

        $this->_init();

        return true;
    }

    private function zipWriteLocalEntry($fp, Dapphp_PhpZipUtils_ZipDirectoryEntry $local, Dapphp_PhpZipUtils_ZipDirectoryEntry $central = null)
    {
        $headerPos = ftell($fp);

        fwrite($fp, self::LOCAL_MAGIC);
        fwrite($fp, pack('vvvvvVVVvv',
                $local->versionNeeded,
                $local->bitFlags,
                $local->compressionMethod,
                $local->time,
                $local->date,
                $local->crc,
                $local->csize,
                $local->size,
                $local->filenameLength,
                $local->extraFieldLength
        ));

        fwrite($fp, $local->filename);

        if ($local->extraFieldLength) {
            fwrite($fp, $local->extraFieldData);
        }

        if ($local->state == Dapphp_PhpZipUtils_ZipDirectoryEntry::ZIP_ST_UNCHANGED || $local->state == Dapphp_PhpZipUtils_ZipDirectoryEntry::ZIP_ST_RENAMED) {
            if (!$this->fseek($central->offset + self::LENTRYSIZE + $local->filenameLength + $local->extraFieldLength, SEEK_SET)) {
                return $this->_setError(self::ER_SEEK, 'Failed to seek to local file offset when writing local entry');
            }

            $left     = $local->csize;
            $readsize = 4096;

            while ($left > 0) {
                $read = ($readsize >= $left) ? $readsize : $left;
                $data = $this->_readFromStream($read);
                fwrite($fp, $data);
                $left -= strlen($data);
            }
        } else {
            switch($local->compressionMethod) {
                case self::CM_STORE:
                    $data = $local->data;
                    break;

                case self::CM_DEFLATE:
                    $data = gzdeflate($local->data);
                    break;

                case self::CM_BZIP2:
                    $data = bzcompress($local->data);
                    break;
            }

            if (strlen($this->getArchivePassword())) {
                $this->encryptFileData($data, $local);

                $local->bitFlags |= Dapphp_PhpZipUtils_ZipFile::ZIP_GPBF_ENCRYPTED;
            }

            $local->csize = strlen($data);

            fwrite($fp, $data);

            fseek($fp, $headerPos + 6);
            fwrite($fp, pack('v', $local->bitFlags));

            fseek($fp, $headerPos + 18);
            fwrite($fp, pack('V', $local->csize));
            fseek($fp, 0, SEEK_END);
        }

        return true;
    }

    private function zipWriteCentralDirectory($fp, Dapphp_PhpZipUtils_ZipCentralDirectory $cd)
    {
        $size = 0;

        for ($i = 0; $i < $cd->getNentry(); ++$i) {
            /* @var $entry Dapphp_PhpZipUtils_ZipDirectoryEntry */
            $entry = $cd->getEntryIndex($i);

            fwrite($fp, self::CENTRAL_MAGIC);
            fwrite($fp, pack('vvvvvvVVVvvvvvVV',
                    $entry->versionMadeBy,
                    $entry->versionNeeded,
                    $entry->bitFlags,
                    $entry->compressionMethod,
                    $entry->time,
                    $entry->date,
                    $entry->crc,
                    $entry->csize,
                    $entry->size,
                    strlen($entry->filename),
                    strlen($entry->extraFieldData),
                    strlen($entry->comment),
                    $entry->diskNumber,
                    $entry->internalAttributes,
                    $entry->externalAttributes,
                    $entry->offset
            ));

            fwrite($fp, $entry->filename);

            if (strlen($entry->extraFieldData))
                fwrite($fp, $entry->extraFieldData);

            if (strlen($entry->comment))
                fwrite($fp, $entry->comment);

            $size += self::CDENTRYSIZE + strlen($entry->filename) + strlen($entry->extraFieldData) + strlen($entry->comment);
        }

        return $size;
    }

    private function _setError($errorCode, $errorMessage)
    {
        $this->_error        = $errorCode;
        $this->_errorMessage = $errorMessage;
        return false;
    }

    protected function _writeDataToFile(ZipFileEntry &$file)
    {
        if (empty($file->path)) {
            $path = $this->_outputPath;
        } else {
            $path = $this->_outputPath . DIRECTORY_SEPARATOR . $file->path;   // prepend output path to zip file path
        }

        if (DIRECTORY_SEPARATOR != '/') {
            $path = str_replace('/', DIRECTORY_SEPARATOR, $path); // windows
        }

        if ($file->is_directory) {
            $path .= DIRECTORY_SEPARATOR . $file->name; // create directory
        }

        $directory_string = ''; // used for paths in the zip file
        if ($path != '') {
            clearstatcache();
            $directories = explode(DIRECTORY_SEPARATOR, $path); // get a path for the zip file

            foreach($directories as $directory) { // iterate over each directory and concatenate the directory string
                if (empty($directory)) {
                    $directory_string .= DIRECTORY_SEPARATOR;
                    continue;
                }

                $directory_string .= $directory;

                if (!file_exists($directory_string)) {
                    // need to create a directory in the output path
                    if (!mkdir($directory_string, 0775)) {
                        // failed to create the directory, permissions or invalid path
                        $file->error = self::ER_OPEN;
                        $file->errorMessage = 'Unable to create directory "' . htmlspecialchars($directory_string) . '"';
                        return false;
                    }
                    //added by Cyrill Helg
                    array_push($this->_createdDirectories, $directory_string);
                }
                $directory_string .= DIRECTORY_SEPARATOR; // append trailing slash for next round
            }
        }

        if (!$file->is_directory) {
            $filename = $directory_string . $file->name;
            if (file_exists($filename) && $this->getOverwriteExisting() == false) {
                // don't overwrite existing files
                $file->error = self::ER_EXISTS;
                $file->errorMessage = 'File "' . htmlspecialchars($filename) . '" already exists';
                return false;
            }

            $fp = fopen($filename, 'w+b'); // open output file
            if (!$fp) {
                $file->error = self::ER_OPEN;
                $file->errorMessage = 'Failed to open "' . htmlspecialchars($filename) . '" for writing';
                return false;
            }

            if (strlen($file->data)) {
                fwrite($fp, $file->data);
            }

            fclose($fp);

            if ($file->time != null) touch($filename, $file->time); // set mtime

            //added by Cyrill Helg
            $this->_createdFiles[] = array(
                    'filename' => $file->name,
                    'path'     => $directory_string
            );
        }

        return true;
    }

    private function fseek($offset, $whence)
    {
        if ($this->_fromFile) {
            return fseek($this->_fp, $offset, $whence) === 0;
        } else {
            if ($whence == SEEK_END) {
                if ($offset > 0) {
                    return false;
                }
                if (strlen($this->_fileData) < abs($offset)) {
                    return false;
                }
                $this->_fp = strlen($this->_fileData) + $offset;
            } else if ($whence == SEEK_SET) {
                if ($offset < 0) {
                    return false;
                }
                if (strlen($this->_fileData) < $offset) {
                    return false;
                }
                $this->_fp = $offset;
            } else if ($whence == SEEK_CUR) {
                if ($offset >= 0) {
                    if ($this->_fp + $offset > strlen($this->_fileData)) {
                        return false;
                    }
                } else {
                    if ($this->_fp + $offset < 0) {
                        return false;
                    }
                }
                $this->_fp += $offset;
            } else {
                return false;
            }
        }
        return true;
    }

    private function ftello()
    {
        if ($this->_fromFile) {
            return ftell($this->_fp);
        } else {
            return $this->_fp;
        }
    }

    protected function _isEof()
    {
        if ($this->_fromFile) {
            $eof = feof($this->_fp);
        } else {
            $eof = strlen($this->_fileData) <= $this->_fp;
        }

        return $eof;
    }

    protected function _checkEnd($data, $length)
    {
        if (strlen($data) < $length) {
            $this->_state = self::ST_EOF;
            if ($this->_fromFile) {
                $offset = ftell($this->_fp) - strlen($this->_readBuffer);
            } else {
                $offset = $this->_fp - strlen($this->_readBuffer);
            }

            return $this->_setError(self::ER_EOF, 'Unexpected end of file at offset ' . $offset);
        }

        return true;
    }

    protected function _readBytes($length)
    {
        if ($length < 1) return '';

        if (empty($this->_readBuffer) && $this->getReadBufferSize() > 0) {
            $this->_readBuffer = $this->_readFromStream($this->getReadBufferSize());
        }

        if (!empty($this->_readBuffer) && strlen($this->_readBuffer) < $length) {
            $this->_readBuffer .= $this->_readFromStream($length - strlen($this->_readBuffer));
        }

        if (!empty($this->_readBuffer)) {
            $data          = substr($this->_readBuffer, 0, $length);
            $this->_readBuffer = substr($this->_readBuffer, $length);
        } else {
            $data = $this->_readFromStream($length);
        }

        return $data;
    }

    protected function _readFromStream($length)
    {
        if ($this->_fromFile) {
            $data = fread($this->_fp, $length);
        } else {
            if ($this->_fp >= strlen($this->_fileData)) {
                $data   = false;
                $length = 0;
            } else if (strlen($this->_fileData) - $this->_fp < $length) {
                $length = strlen($this->_fileData) - $this->_fp;
            }

            $data = substr($this->_fileData, $this->_fp, $length);
            $this->_fp += strlen($data);
        }

        return $data;
    }

    protected function parseExtraExtendedTimestamp(ZipFileEntry &$file, $data, $size)
    {
        /*
         Flags         Byte        info bits
         (ModTime)     Long        time of last modification (UTC/GMT)
         (AcTime)      Long        time of last access (UTC/GMT)
         (CrTime)      Long        time of original creation (UTC/GMT)
         */
        $format = 'cBITS';

        if ($size >= 5) {
            $format .= '/VMTIME';
        }
        if ($size >= 9) {
            $format .= '/VATIME';
        }
        if ($size >= 13) {
            $format .= '/VCTIME';
        }

        $tsinfo = unpack($format, $data);

        if (isset($tsinfo['MTIME'])) $file->ut_mtime = $tsinfo['MTIME'];
        if (isset($tsinfo['ATIME'])) $file->ut_atime = $tsinfo['ATIME'];
        if (isset($tsinfo['CTIME'])) $file->ut_ctime = $tsinfo['CTIME'];

        return true;
    }

    protected function parseExtraUnixOwnerInfo(ZipFileEntry &$file, $data, $size)
    {
        /*
         TSize         Short       total data size for this block
         Version       1 byte      version of this extra field, currently 1
         UIDSize       1 byte      Size of UID field
         UID           Variable    UID for this entry
         GIDSize       1 byte      Size of GID field
         GID           Variable    GID for this entry
         */
        $formats = array(1 => 'c', 2 => 'v', 4 => 'V');

        $version = ord(substr($data, 0, 1));

        if ($version !== 1) {
            return false;
        }

        $uidsize = ord(substr($data, 1, 1));
        $uid     = substr($data, 2, $uidsize);
        $gidsize = ord(substr($data, 2 + $uidsize, 1));
        $gid     = substr($data, 2 + $uidsize + 1, $gidsize);

        $uid = unpack($formats[$uidsize] . 'UID', $uid);
        $gid = unpack($formats[$gidsize] . 'GID', $gid);

        $file->uid = $uid['UID'];
        $file->gid = $gid['GID'];

        return true;
    }
}

