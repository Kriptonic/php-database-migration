<?php

namespace Migrate;

use Cocur\Slugify\Slugify;
use Migrate\Utils\ArrayUtil;

/**
 * Class Migration
 *
 * @package Migrate
 *
 * @author https://github.com/alwex
 * @author Christopher Sharman <chrstopher.p.sharman@gmail.com>
 */
class Migration
{
    private $id;
    private $description;
    private $file;
    private $appliedAt;
    private $version;
    private $sqlUp;
    private $sqlDown;
    private $isRemoteOnly = false;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return mixed
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param mixed $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * @return mixed
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * @param mixed $file
     */
    public function setFile($file)
    {
        $this->file = $file;
    }

    /**
     * @return mixed
     */
    public function getAppliedAt()
    {
        return $this->appliedAt;
    }

    /**
     * @param mixed $appliedAt
     */
    public function setAppliedAt($appliedAt)
    {
        $this->appliedAt = $appliedAt;
    }

    /**
     * @return mixed
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @param mixed $version
     */
    public function setVersion($version)
    {
        $this->version = $version;
    }

    /**
     * @return mixed
     */
    public function getSqlUp()
    {
        return $this->sqlUp;
    }

    /**
     * @param mixed $sqlUp
     */
    public function setSqlUp($sqlUp)
    {
        $this->sqlUp = $sqlUp;
    }

    /**
     * @return mixed
     */
    public function getSqlDown()
    {
        return $this->sqlDown;
    }

    /**
     * @param mixed $sqlDown
     */
    public function setSqlDown($sqlDown)
    {
        $this->sqlDown = $sqlDown;
    }

    /**
     * @return bool
     */
    public function isRemoteOnly()
    {
        return $this->isRemoteOnly;
    }

    /**
     * @param bool $isRemoteOnly
     */
    public function setIsRemoteOnly($isRemoteOnly)
    {
        $this->isRemoteOnly = $isRemoteOnly;
    }

    /**
     * Get the status of this migration.
     *
     * One of the following status can be returned:
     * 'PENDING' this is a local migration not on the remote database.
     * 'MIGRATED' is for a local migration that is found on the remote.
     * 'REMOTE ONLY' returned when a remote migration cannot be found locally.
     *
     * @return string The migration status.
     */
    public function getStatus()
    {
        if ($this->isRemoteOnly) {
            return 'REMOTE ONLY';
        } elseif ($this->getAppliedAt() !== null) {
            return 'MIGRATED';
        } else {
            return 'PENDING';
        }
    }

    /**
     * Create a migration object from a file.
     *
     * @param string $filename The name of the migration file to use.
     * @param string $migrationDir The path to the migration files.
     * @return Migration The created Migration object.
     */
    public static function createFromFile($filename, $migrationDir)
    {
        $data = explode('_', $filename);

        $migration = new self();
        $migration->setId($data[0]);
        $migration->setAppliedAt(null);
        $migration->setVersion(null);
        $migration->setDescription(str_replace('.sql', '', str_replace('-', ' ', $data[1])));
        $migration->setFile($filename);

        // Retrieve the up and down SQL content from the migration file.
        $migration->load($migrationDir);

        return $migration;
    }

    /**
     * Create a migration object from an array.
     *
     * @param array $data Migration data.
     * @param string $migrationDir The path to the migration folder.
     * @return Migration|null The Migration object if created successfully; null otherwise.
     */
    public static function createFromRow(array $data, $migrationDir)
    {
        $migration = new self();
        $migration->setId(ArrayUtil::get($data, 'id'));
        $migration->setAppliedAt(ArrayUtil::get($data, 'applied_at'));
        $migration->setVersion(ArrayUtil::get($data, 'version'));
        $migration->setDescription(ArrayUtil::get($data, 'description'));

        $slugger = new Slugify();
        $filename = $migration->getId() . '_' . $slugger->slugify($migration->getDescription()) . '.sql';
        $migration->setFile($filename);

        if (file_exists($migrationDir . '/' . $filename)) {
            $migration->load($migrationDir);
        } else {
            $migration->setIsRemoteOnly(true);
        }

        return $migration;
    }

    /**
     * Convert to the migration object to an array.
     *
     * @return array An array representation of this migration object.
     */
    public function toArray()
    {
        return array(
            $this->getId(),
            $this->getVersion(),
            $this->getAppliedAt(),
            $this->getDescription(),
            $this->getStatus()
        );
    }

    /**
     * Load the up and down SQL from the migration file.
     *
     * @param string $migrationDir Path to the migrations folder.
     */
    public function load($migrationDir)
    {
        $content = file_get_contents($migrationDir . '/' . $this->getFile());
        if ($content && strpos($content, '@UNDO') > 0) {
            $sql = explode('-- @UNDO', $content);
            $this->setSqlUp($sql[0]);
            $this->setSqlDown($sql[1]);
        }
    }
}
