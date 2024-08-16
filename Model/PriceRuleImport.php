<?php

namespace MalibuCommerce\MConnect\Model;

use Magento\Framework\DataObject;
use Magento\Framework\DataObject\IdentityGeneratorInterface;

class PriceRuleImport extends DataObject
{
    /**#@+
     * Constants for keys of data array. Identical to the name of the getter in snake case
     */
    public const UUID = 'uuid';
    public const WEBSITE_ID = 'website_id';
    public const STATUS = 'status';
    public const PROCESSED_COUNT = 'processed_count';
    public const ATTEMPTS = 'attempts';
    public const MESSAGE = 'message';

    public const FILENAME = 'filename';
    public const CREATED_AT = 'created_at';
    public const EXECUTED_AT = 'executed_at';
    /**#@-*/

    /**#@+
     * Status types
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_FAILED = 'failed';
    public const STATUS_COMPLETE = 'complete';
    /**#@-*/

    public const CSV_HEADERS = [
        'Unique ID',
        'Last Modified Date_Time',
        'Variant Code',
        'Unit of Measure Code',
        'Ending Date',
        'Minimum Quantity',
        'Allow Invoice Disc_',
        'Unit Price',
        'Starting Date',
        'Currency Code',
        'Sales Code',
        'Item No_',
    ];

    /**
     * @var IdentityGeneratorInterface
     */
    private IdentityGeneratorInterface $identityService;

    /**
     * @param IdentityGeneratorInterface $identityService
     * @param array $data
     */
    public function __construct(
        IdentityGeneratorInterface $identityService,
        array $data = []
    ) {
        if (empty($data[self::UUID])) {
            $data[self::UUID] = $identityService->generateId();
        }

        if (empty($data[self::STATUS])) {
            $data[self::STATUS] = self::STATUS_PENDING;
        }

        parent::__construct($data);
        $this->identityService = $identityService;
    }

    /**
     * @return string
     */
    public function getUuid(): string
    {
        if (empty($this->getData(self::UUID))) {
            $this->setData(self::UUID, $this->identityService->generateId());
        }
        return (string)$this->getData(self::UUID);
    }

    /**
     * @param string $uuid
     * @return void
     */
    public function setUuid(string $uuid): void
    {
        $this->setData(self::UUID, $uuid);
    }

    /**
     * @return int
     */
    public function getWebsiteId(): int
    {
        return (int)$this->getData(self::WEBSITE_ID);
    }

    /**
     * @param int $websiteId
     * @return void
     */
    public function setWebsiteId(int $websiteId): void
    {
        $this->setData(self::WEBSITE_ID, $websiteId);
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return (string)$this->getData(self::STATUS);
    }

    /**
     * @param string $status
     * @return void
     */
    public function setStatus(string $status): void
    {
        $this->setData(self::STATUS, $status);
    }

    /**
     * @return int
     */
    public function getProcessedCount(): int
    {
        return (int)$this->getData(self::PROCESSED_COUNT);
    }

    /**
     * @param int $processedCount
     * @return void
     */
    public function setProcessedCount(int $processedCount): void
    {
        $this->setData(self::PROCESSED_COUNT, $processedCount);
    }

    /**
     * @return int
     */
    public function getAttempts(): int
    {
        return (int)$this->getData(self::ATTEMPTS);
    }

    /**
     * @param int $attempts
     * @return void
     */
    public function setAttempts(int $attempts): void
    {
        $this->setData(self::ATTEMPTS, $attempts);
    }

    /**
     * @return string|null
     */
    public function getFilename(): ?string
    {
        return $this->getData(self::FILENAME);
    }

    /**
     * @param string|null $filename
     * @return void
     */
    public function setFilename(?string $filename): void
    {
        $this->setData(self::FILENAME, $filename);
    }

    /**
     * @return string|null
     */
    public function getMessage(): ?string
    {
        return $this->getData(self::MESSAGE);
    }

    /**
     * @param string|null $message
     * @return void
     */
    public function setMessage(?string $message = null): void
    {
        $this->setData(self::MESSAGE, $message);
    }

    /**
     * @return string|null
     */
    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * @param string|null $createdAt
     * @return void
     */
    public function setCreatedAt(?string $createdAt): void
    {
        $this->setData(self::CREATED_AT, $createdAt);
    }

    /**
     * @return string|null
     */
    public function getExecutedAt(): ?string
    {
        return $this->getData(self::EXECUTED_AT);
    }

    /**
     * @param string|null $executedAt
     * @return void
     */
    public function setExecutedAt(?string $executedAt): void
    {
        $this->setData(self::EXECUTED_AT, $executedAt);
    }
}
