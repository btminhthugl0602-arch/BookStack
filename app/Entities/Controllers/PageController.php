<?php

namespace BookStack\Entities\Controllers;
use Illuminate\Support\Facades\DB;
use BookStack\Activity\Models\View as ActivityView;
use Illuminate\Support\Facades\Log;
use BookStack\Activity\Models\View;
use BookStack\Activity\Tools\CommentTree;
use BookStack\Activity\Tools\UserEntityWatchOptions;
use BookStack\Entities\Models\Book;
use BookStack\Entities\Models\Chapter;
use BookStack\Entities\Queries\EntityQueries;
use BookStack\Entities\Queries\PageQueries;
use BookStack\Entities\Repos\PageRepo;
use BookStack\Entities\Tools\BookContents;
use BookStack\Entities\Tools\Cloner;
use BookStack\Entities\Tools\NextPreviousContentLocator;
use BookStack\Entities\Tools\PageContent;
use BookStack\Entities\Tools\PageEditActivity;
use BookStack\Entities\Tools\PageEditorData;
use BookStack\Exceptions\NotFoundException;
use BookStack\Exceptions\PermissionsException;
use BookStack\Http\Controller;
use BookStack\Permissions\Permission;
use BookStack\References\ReferenceFetcher;
use Exception;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function __construct(
        protected PageRepo $pageRepo,
        protected PageQueries $queries,
        protected EntityQueries $entityQueries,
        protected ReferenceFetcher $referenceFetcher
    ) {
    }

    /**
     * FIX LỖI HÌNH 2: Hiển thị trình soạn thảo bản nháp
     */
    public function editDraft(Request $request, string $bookSlug, int $pageId)
    {
        $draft = $this->queries->findVisibleByIdOrFail($pageId);
        $this->checkOwnablePermission(Permission::PageUpdate, $draft);

        $editorData = new PageEditorData($draft, $this->entityQueries, $request->query('editor', ''));
        $this->setPageTitle(trans('entities.pages_editing_draft'));

        return view('pages.edit', $editorData->getViewData());
    }

    /**
     * Lưu trang mới và đưa vào danh sách chờ duyệt
     */
 public function store(Request $request, string $bookSlug, int $pageId)
{
    $this->validate($request, ['name' => ['required', 'string', 'max:255']]);
    $draftPage = $this->queries->findVisibleByIdOrFail($pageId);
    
    // Lưu trang chính thức
    $page = $this->pageRepo->publishDraft($draftPage, $request->all());

    // --- LOGIC PHÂN QUYỀN DUYỆT TỰ ĐỘNG ---
    $user = auth()->user();
    $bookOwnerId = (int)$page->book->owned_by;
    $currentUserId = (int)$user->id;

    // Kiểm tra các cấp bậc: Admin hoặc Chủ sách hoặc Phó Lead
    $isAdmin = $user->hasSystemRole('admin') || $user->email === 'admin@admin.com';
    $isOwner = ($currentUserId === $bookOwnerId);
    $isViceLead = $user->roles()->where('display_name', 'Phó Lead')->exists();

    // Nếu thuộc nhóm quyền lực -> Duyệt luôn
    $status = ($isAdmin || $isOwner || $isViceLead) ? 'da_duyet' : 'cho_duyet';

    DB::table('duyet_bai')->updateOrInsert(
        ['entity_id' => $page->id, 'entity_type' => 'page'],
        [
            'trang_thai' => $status,
            'user_id'    => $currentUserId
           
        ]
    );

    return redirect($page->getUrl());
}

    /**
     * Cập nhật trang và đưa về trạng thái chờ duyệt
     */
    public function update(Request $request, string $bookSlug, string $pageSlug)
{
    $this->validate($request, ['name' => ['required', 'string', 'max:255']]);
    $page = $this->queries->findVisibleBySlugsOrFail($bookSlug, $pageSlug);
    $this->checkOwnablePermission(Permission::PageUpdate, $page);

    $this->pageRepo->update($page, $request->all());

    $isBookOwner = auth()->id() == $page->book->owned_by;
    $trangThai = $isBookOwner ? 'da_duyet' : 'cho_duyet';

    DB::table('duyet_bai')->updateOrInsert(
        ['entity_id' => $page->id, 'entity_type' => 'page'],
        ['trang_thai' => $trangThai, 'user_id' => auth()->id()]
    );

    return redirect($page->getUrl());
}

    /**
     * Hiển thị nội dung trang (Có kiểm tra trạng thái duyệt)
     */
   public function show(string $bookSlug, string $pageSlug)
{
    $page = $this->queries->findVisibleBySlugsOrFail($bookSlug, $pageSlug);
    
    // 1. KIỂM TRA TRẠNG THÁI DUYỆT
    $statusCheck = DB::table('duyet_bai')
        ->where('entity_id', $page->id)
        ->where('entity_type', 'page')
        ->first();
    $status = $statusCheck ? $statusCheck->trang_thai : 'cho_duyet';

    // 2. XÁC ĐỊNH QUYỀN
    $user = auth()->user();
    $isAdmin = $user && ($user->hasSystemRole('admin') || $user->email === 'admin@admin.com');
    $isBookOwner = $user && ((int)$user->id === (int)$page->book->owned_by);
    $isViceLead = $user && $user->roles()->where('display_name', 'Phó Lead')->exists();
    $isAuthor = $user && ((int)$user->id === (int)$page->created_by);

    $alertHtml = "";

    if ($status === 'cho_duyet') {
        if ($isAdmin || $isBookOwner || $isViceLead) {
            // Hiển thị nút duyệt cho cấp quản lý
            $alertHtml = "
            <div style='background:#fff3cd; color:#856404; padding:15px; border:1px solid #ffeeba; margin-bottom:20px; border-radius:4px; display:flex; justify-content:space-between; align-items:center;'>
                <span>⚠️ <b>Thông báo:</b> Trang này đang chờ bạn duyệt.</span>
                <form action='".url('/approve-content')."' method='POST' style='margin:0;'>
                    <input type='hidden' name='_token' value='".csrf_token()."'>
                    <input type='hidden' name='id' value='".$page->id."'>
                    <input type='hidden' name='type' value='page'>
                    <button type='submit' style='background:#28a745; color:white; border:none; padding:7px 15px; border-radius:4px; cursor:pointer; font-weight:bold;'>✅ DUYỆT BÀI</button>
                </form>
            </div>";
        } elseif ($isAuthor) {
            // Thông báo cho tác giả
            $alertHtml = "<div style='background:#d1ecf1; color:#0c5460; padding:15px; border:1px solid #bee5eb; margin-bottom:20px; border-radius:4px;'>ℹ️ Bài viết của bạn đang chờ Leader phê duyệt.</div>";
        } else {
            // Người khác: Hiện màn hình khóa
            return response($this->getLockScreenHtml($page->name), 403)->header('Content-Type', 'text/html');
        }
    }

    // 3. RENDER NỘI DUNG (Nếu vượt qua các bước kiểm tra trên)
    $pageContent = (new PageContent($page));
    
    // Chèn thông báo duyệt vào đầu nội dung trang
    $page->html = $alertHtml . $pageContent->render();
    
    $sidebarTree = (new BookContents($page->book))->getTree();
    $commentTree = (new CommentTree($page));
    $nextPreviousLocator = new NextPreviousContentLocator($page, $sidebarTree);

    // Sử dụng ActivityView (Alias đã đặt ở đầu file) để tránh lỗi
    ActivityView::incrementFor($page);
    $this->setPageTitle($page->getShortName());

    return view('pages.show', [
        'page'            => $page,
        'book'            => $page->book,
        'current'         => $page,
        'sidebarTree'     => $sidebarTree,
        'commentTree'     => $commentTree,
        'pageNav'         => $pageContent->getNavigation($page->html),
        'watchOptions'    => new UserEntityWatchOptions(user(), $page),
        'next'            => $nextPreviousLocator->getNext(),
        'previous'        => $nextPreviousLocator->getPrevious(),
        'referenceCount'  => $this->referenceFetcher->getReferenceCountToEntity($page),
    ]);
}

// Hàm bổ trợ màn hình khóa (Nên để riêng cho code sạch)
private function getLockScreenHtml($name) {
    return "
    <div style='display: flex; justify-content: center; align-items: center; height: 100vh; font-family: sans-serif; background-color: #f7fafc;'>
        <div style='text-align: center; padding: 40px; background: white; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); max-width: 500px;'>
            <div style='font-size: 60px; margin-bottom: 20px;'>🔒</div>
            <h1 style='color: #e53e3e; margin-bottom: 10px; font-size: 24px;'>Nội dung đang chờ phê duyệt</h1>
            <p style='color: #4a5568;'>Trang <strong>\"$name\"</strong> hiện đang chờ kiểm duyệt nội dung.</p>
            <a href='".url('/')."' style='display: inline-block; margin-top: 20px; padding: 10px 20px; background-color: #448aff; color: white; text-decoration: none; border-radius: 4px;'>Quay lại trang chủ</a>
        </div>
    </div>";
}

    /**
     * Khởi tạo tạo trang mới (Draft)
     */
    public function create(string $bookSlug, ?string $chapterSlug = null)
    {
        $parent = $chapterSlug 
            ? $this->entityQueries->chapters->findVisibleBySlugsOrFail($bookSlug, $chapterSlug) 
            : $this->entityQueries->books->findVisibleBySlugOrFail($bookSlug);

        $this->checkOwnablePermission(Permission::PageCreate, $parent);

        if ($this->isSignedIn()) {
            $draft = $this->pageRepo->getNewDraftPage($parent);
            return redirect($draft->getUrl());
        }

        return view('pages.guest-create', ['parent' => $parent]);
    }

    // --- Các phương thức bổ sung để tránh lỗi thiếu hàm ---
    public function edit(Request $request, string $bookSlug, string $pageSlug) {
        $page = $this->queries->findVisibleBySlugsOrFail($bookSlug, $pageSlug);
        $editorData = new PageEditorData($page, $this->entityQueries, $request->query('editor', ''));
        return view('pages.edit', $editorData->getViewData());
    }

    public function destroy(string $bookSlug, string $pageSlug) {
        $page = $this->queries->findVisibleBySlugsOrFail($bookSlug, $pageSlug);
        $this->checkOwnablePermission(Permission::PageDelete, $page);
        $parent = $page->getParent(); 
        $this->pageRepo->destroy($page);
        DB::table('duyet_bai')->where('entity_id', $page->id)->where('entity_type', 'page')->delete();
        return redirect($parent->getUrl());
    }

    public function copy(Request $request, Cloner $cloner, string $bookSlug, string $pageSlug) {
        $page = $this->queries->findVisibleBySlugsOrFail($bookSlug, $pageSlug);
        $newParent = $page->getParent();
        $pageCopy = $cloner->clonePage($page, $newParent, $request->get('name') ?: $page->name);
        DB::table('duyet_bai')->insert(['entity_id' => $pageCopy->id, 'entity_type' => 'page', 'trang_thai' => 'cho_duyet', 'user_id' => auth()->id()]);
        return redirect($pageCopy->getUrl());
    }
}
