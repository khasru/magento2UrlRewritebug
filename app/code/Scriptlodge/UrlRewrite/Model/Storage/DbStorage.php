<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Scriptlodge\UrlRewrite\Model\Storage;

use Magento\Framework\DB\Select;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite as UrlRewriteData;

class DbStorage extends \Magento\UrlRewrite\Model\Storage\DbStorage
{
    /**
     * @param UrlRewrite[] $urls
     *
     * @return void
     */
    private function deleteOldUrls(array $urls)
    {
        $oldUrlsSelect = $this->connection->select();
        $oldUrlsSelect->from(
            $this->resource->getTableName(self::TABLE_NAME)
        );
        /** @var UrlRewrite $url */
        foreach ($urls as $url) {
            $oldUrlsSelect->orWhere(
                $this->connection->quoteIdentifier(
                    UrlRewrite::ENTITY_TYPE
                ) . ' = ?',
                $url->getEntityType()
            );
            $oldUrlsSelect->where(
                $this->connection->quoteIdentifier(
                    UrlRewrite::ENTITY_ID
                ) . ' = ?',
                $url->getEntityId()
            );
            $oldUrlsSelect->where(
                $this->connection->quoteIdentifier(
                    UrlRewrite::STORE_ID
                ) . ' = ?',
                $url->getStoreId()
            );
        }

        // prevent query locking in a case when nothing to delete
        $checkOldUrlsSelect = clone $oldUrlsSelect;
        $checkOldUrlsSelect->reset(Select::COLUMNS);
        $checkOldUrlsSelect->columns('count(*)');
        $hasOldUrls = (bool)$this->connection->fetchOne($checkOldUrlsSelect);

        if ($hasOldUrls) {
            $this->connection->query(
                $oldUrlsSelect->deleteFromSelect(
                    $this->resource->getTableName(self::TABLE_NAME)
                )
            );
        }
    }

    public function doFindAllByDataUrl(array $urls)
    {
        $oldUrlsSelect = $this->connection->select();
        $oldUrlsSelect->from(
            $this->resource->getTableName(self::TABLE_NAME)
        );

        /** @var UrlRewrite $url */
        foreach ($urls as $url) {
            $oldUrlsSelect->orWhere(
                $this->connection->quoteIdentifier(
                    UrlRewrite::ENTITY_TYPE
                ) . ' = ?',
                $url->getEntityType()
            );

            $oldUrlsSelect->where(
                $this->connection->quoteIdentifier(
                    UrlRewrite::ENTITY_ID
                ) . ' = ?',
                $url->getEntityId()
            );
            $oldUrlsSelect->where(
                $this->connection->quoteIdentifier(
                    UrlRewrite::STORE_ID
                ) . ' = ?',
                $url->getStoreId()
            );
        }
        $__hasOldUrls = $this->connection->fetchAll($oldUrlsSelect);
        $data=[];
        if(count($__hasOldUrls)>0) {
            foreach ($__hasOldUrls as $hasOldUrl) {
                $data[] = $hasOldUrl['request_path'];
            }
        }
        return $data;

        //return $hasOldUrls = $this->connection->fetchAll($oldUrlsSelect);

    }


    /**
     * @inheritDoc
     */
    protected function doReplace(array $urls)
    {
        $this->deleteOldUrls($urls);
        //$foundurls= $this->doFindAllByDataUrl($urls);
        $data = [];
        $urlConflicted = [];
        foreach ($urls as $url) {
            $urlFound = $this->doFindOneByData(
                [
                    UrlRewriteData::REQUEST_PATH => $url->getRequestPath(),
                    UrlRewriteData::STORE_ID => $url->getStoreId(),
                ]
            );
            if (isset($urlFound[UrlRewriteData::URL_REWRITE_ID])) {
                $urlConflicted[$urlFound[UrlRewriteData::URL_REWRITE_ID]] = $url->toArray();
                continue;
            } else {
                $data[] = $url->toArray();
            }

           /*if(!in_array($url->getRequestPath(),$foundurls)){
               $data[] = $url->toArray();
           }else{
               continue;
           }*/
        }

        try {
            $this->insertMultiple($data);
        } catch (\Magento\Framework\Exception\AlreadyExistsException $e) {
            /** @var \Magento\UrlRewrite\Service\V1\Data\UrlRewrite[] $urlConflicted */
            $urlConflicted = [];
            foreach ($urls as $url) {
                $urlFound = $this->doFindOneByData(
                    [
                        UrlRewriteData::REQUEST_PATH => $url->getRequestPath(),
                        UrlRewriteData::STORE_ID => $url->getStoreId(),
                    ]
                );
                if (isset($urlFound[UrlRewriteData::URL_REWRITE_ID])) {
                    $urlConflicted[$urlFound[UrlRewriteData::URL_REWRITE_ID]] = $url->toArray();
                }
            }
            if ($urlConflicted) {
                throw new \Magento\UrlRewrite\Model\Exception\UrlAlreadyExistsException(
                    __('URL key for specified store already exists.'),
                    $e,
                    $e->getCode(),
                    $urlConflicted
                );
            } else {
                throw $e->getPrevious() ?: $e;
            }
        }

        return $urls;
    }

}
