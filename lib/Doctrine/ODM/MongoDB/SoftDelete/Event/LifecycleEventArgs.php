<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
*/

namespace Doctrine\ODM\MongoDB\SoftDelete\Event;

use Doctrine\ODM\MongoDB\SoftDelete\SoftDeleteable;
use Doctrine\ODM\MongoDB\SoftDelete\SoftDeleteManager;
use Doctrine\Common\EventArgs;

/**
 * Lifecycle Events are triggered by the UnitOfWork during lifecycle transitions
 * of documents.
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.com
 * @since       1.0
 * @author      Jonathan H. Wage <jonwage@gmail.com>
 */
class LifecycleEventArgs extends EventArgs
{
    /**
     * @var SoftDeleteManager $sdm
     */
    private $sdm;

    /**
     * @var SoftDeleteable $document
     */
    private $document;
    
    public function __construct(SoftDeleteable $document, SoftDeleteManager $sdm)
    {
        $this->document = $document;
        $this->sdm = $sdm;
    }

    /**
     * Gets the SoftDeletable document instance.
     *
     * @return SoftDeleteable $document
     */
    public function getDocument()
    {
        return $this->document;
    }

    /**
     * Gets the SoftDeleteManager
     *
     * @return SoftDeleteManager
     */
    public function getSoftDeleteManager()
    {
        return $this->sdm;
    }
}