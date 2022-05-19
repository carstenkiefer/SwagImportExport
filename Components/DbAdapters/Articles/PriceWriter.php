<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagImportExport\Components\DbAdapters\Articles;

use Enlight_Components_Db_Adapter_Pdo_Mysql as PDOConnection;
use Shopware\Models\Article\Price;
use SwagImportExport\Components\DataManagers\Articles\PriceDataManager;
use SwagImportExport\Components\DbalHelper;
use SwagImportExport\Components\Exception\AdapterException;
use SwagImportExport\Components\Utils\SnippetsHelper;
use SwagImportExport\Components\Utils\SwagVersionHelper;
use SwagImportExport\Components\Validators\Articles\PriceValidator;

class PriceWriter
{
    protected PDOConnection $db;

    protected array $customerGroups;

    protected PriceValidator $validator;

    protected PriceDataManager $dataManager;

    private DbalHelper $dbalHelper;

    /**
     * initialises the class properties
     */
    public function __construct(
        PDOConnection $db,
        DbalHelper $dbalHelper,
        PriceDataManager $dataManager
    ) {
        $this->validator = new PriceValidator();
        $this->db = $db;
        $this->dbalHelper = $dbalHelper;
        $this->dataManager = $dataManager;
        $this->customerGroups = $this->getCustomerGroup();
    }

    /**
     * @throws AdapterException
     */
    public function write($articleId, $articleDetailId, $prices)
    {
        $tax = $this->getArticleTaxRate($articleId);

        foreach ($prices as $price) {
            $orderNumber = $this->getArticleOrderNumber($articleDetailId);
            $price = $this->validator->filterEmptyString($price);
            $price = $this->dataManager->setDefaultFields($price);
            $this->validator->checkRequiredFields($price, $orderNumber);
            $this->validator->validate($price, PriceValidator::$mapper);

            $this->checkRequirements($price, $orderNumber);

            // skip empty prices for non-default customer groups
            if (empty($price['price']) && $price['priceGroup'] !== 'EK') {
                continue;
            }

            $priceId = (int) $this->db->fetchOne(
                '
                    SELECT id
                    FROM s_articles_prices
                    WHERE articleID = ? AND articledetailsID = ? AND pricegroup = ? AND `from` = ?
                ',
                [$articleId, $articleDetailId, $price['priceGroup'], $price['from']]
            );

            if (!$priceId) {
                $price = $this->dataManager->fixDefaultValues($price);
            }

            $newPrice = $priceId == 0;
            $price['articleId'] = $articleId;
            $price['articleDetailsId'] = $articleDetailId;
            $price['customerGroupKey'] = $price['priceGroup'];
            $price = $this->calculatePrice($price, $newPrice, $tax);
            $builder = $this->dbalHelper->getQueryBuilderForEntity($price, Price::class, $priceId);
            $builder->execute();
        }
    }

    protected function calculatePrice($price, $newPrice, $tax)
    {
        $taxInput = $this->customerGroups[$price['priceGroup']];

        $price['price'] = $this->formatToFloatValue($price['price']);

        if (isset($price['purchasePrice'])) {
            $price['purchasePrice'] = $this->formatToFloatValue($price['purchasePrice']);
        }

        if (isset($price['pseudoPrice'])) {
            $price['pseudoPrice'] = $this->formatToFloatValue($price['pseudoPrice']);
        } else {
            if ($newPrice) {
                $price['pseudoPrice'] = 0;
            }

            if ($taxInput) {
                $price['pseudoPrice'] = \round($price['pseudoPrice'] * (100 + $tax) / 100, 2);
            }
        }

        if (SwagVersionHelper::isShopware578()) {
            if (isset($price['regulationPrice'])) {
                $price['regulationPrice'] = $this->formatToFloatValue($price['regulationPrice']);
            } else {
                if ($newPrice) {
                    $price['regulationPrice'] = 0;
                }

                if ($taxInput) {
                    $price['regulationPrice'] = \round($price['regulationPrice'] * (100 + $tax) / 100, 2);
                }
            }
        }

        if (isset($price['percent'])) {
            $price['percent'] = $this->formatToFloatValue($price['percent']);
        }

        if ($taxInput) {
            $price['price'] = $price['price'] / (100 + $tax) * 100;
            $price['pseudoPrice'] = $price['pseudoPrice'] / (100 + $tax) * 100;
            $price['regulationPrice'] = $price['regulationPrice'] / (100 + $tax) * 100;
        }

        return $price;
    }

    /**
     * @throws AdapterException
     */
    protected function checkRequirements($price, $orderNumber)
    {
        if (!\array_key_exists($price['priceGroup'], $this->customerGroups)) {
            $message = SnippetsHelper::getNamespace()->get(
                'adapters/article_customerGroup_not_found',
                'Customer Group by key %s not found for article %s'
            );
            throw new AdapterException(\sprintf($message, $price['priceGroup'], $orderNumber));
        }

        if ($price['from'] <= 0) {
            $message = SnippetsHelper::getNamespace()->get(
                'adapters/articles/invalid_price',
                'Invalid Price "from" value for article %s'
            );
            throw new AdapterException(\sprintf($message, $orderNumber));
        }
    }

    /**
     * @throws AdapterException
     *
     * @return float
     */
    protected function getArticleTaxRate($articleId)
    {
        $sql = 'SELECT coretax.tax FROM s_core_tax AS coretax
                LEFT JOIN s_articles AS article ON article.taxID = coretax.id
                WHERE article.id = ?';
        $tax = $this->db->fetchOne($sql, [$articleId]);

        if (empty($tax)) {
            throw new AdapterException("Tax for article $articleId not found");
        }

        return (float) $tax;
    }

    /**
     * @param int $articleDetailId
     *
     * @return string
     */
    protected function getArticleOrderNumber($articleDetailId)
    {
        $sql = 'SELECT ordernumber FROM s_articles_details WHERE id = ?';

        return $this->db->fetchOne($sql, [$articleDetailId]);
    }

    /**
     * @return array
     */
    private function getCustomerGroup()
    {
        return $this->db->fetchPairs('SELECT groupkey, taxinput FROM s_core_customergroups');
    }

    /**
     * @param string $price
     *
     * @return float
     */
    private function formatToFloatValue($price)
    {
        return (float) \str_replace(',', '.', $price);
    }
}
