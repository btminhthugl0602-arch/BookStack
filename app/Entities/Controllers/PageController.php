<?php

namespace BookStack\Entities\Controllers;

use Illuminate\Support\Facades\DB;
use BookStack\Activity\Models\View as ActivityView;
use BookStack\Activity\Models\View;
use BookStack\Activity\Tools\CommentTree;
use BookStack\Activity\Tools\UserEntityWatchOptions;
use BookStack\Entities\Queries\EntityQueries;
use BookStack\Entities\Queries\PageQueries;
use BookStack\Entities\Repos\PageRepo;
use BookStack\Entities\Tools\BookContents;
use BookStack\Entities\Tools\Cloner;
use BookStack\Entities\Tools\NextPreviousContentLocator;
use BookStack\Entities\Tools\PageContent;
use BookStack\Entities\Tools\PageEditorData;
use BookStack\Http\Controller;
use BookStack\Permissions\Permission;
use BookStack\References\ReferenceFetcher;
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
     * Hiển thị trình soạn thảo bản nháp
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
     * QUAN TRỌNG: Lưu trang mới -> Gọi Trait tự động xử lý duyệt
     */
    public function store(Request $request, string $bookSlug, int $pageId)
    {
        $this->validate($request, ['name' => ['required', 'string', 'max:255']]);
        $draftPage = $this->queries->findVisibleByIdOrFail($pageId);
        
        // 1. Lưu trang chính thức
        $page = $this->pageRepo->publishDraft($draftPage, $request->all());

        // 2. KÍCH HOẠT QUY TRÌNH DUYỆT (Gọi từ Trait)
        // Hàm này sẽ tự check xem user là Sếp hay Nhân viên để set trạng thái da_duyet/cho_duyet
        $page->syncApprovalStatus(); 

        return redirect($page->getUrl());
    }

    /**
     * Cập nhật trang -> Reset trạng thái về chờ duyệt (nếu cần)
     */
    public function update(Request $request, string $bookSlug, string $pageSlug)
    {
        $this->validate($request, ['name' => ['required', 'string', 'max:255']]);
        $page = $this->queries->findVisibleBySlugsOrFail($bookSlug, $pageSlug);
        $this->checkOwnablePermission(Permission::PageUpdate, $page);

        $this->pageRepo->update($page, $request->all());

        // KÍCH HOẠT QUY TRÌNH DUYỆT
        $page->syncApprovalStatus();

        return redirect($page->getUrl());
    }

    /**
     * Hiển thị nội dung trang
     * (Bảo mật đã được xử lý bởi Global Scope ở Entity.php:
     * Nếu chưa duyệt và không phải tác giả/sếp -> Scope tự trả về 404 Not Found)
     */
    public function show(string $bookSlug, string $pageSlug)
    {
        // Nếu user thường truy cập bài chưa duyệt, dòng này sẽ tự ném lỗi 404 (do Scope)
        $page = $this->queries->findVisibleBySlugsOrFail($bookSlug, $pageSlug);
        
        // --- XỬ LÝ HIỂN THỊ THÔNG BÁO (ALERT) CHO SẾP/TÁC GIẢ ---
        $statusCheck = DB::table('duyet_bai')
            ->where('entity_id', $page->id)
            ->where('entity_type', 'page')
            ->first();
        $status = $statusCheck ? $statusCheck->trang_thai : 'cho_duyet';

        $user = auth()->user();
        $alertHtml = "";

        if ($status === 'cho_duyet') {
            // Check quyền Sếp (Logic này nên đồng bộ với Trait, nhưng tạm thời check nhanh ở đây)
            $isBoss = $user && ($user->hasSystemRole('admin') || $user->roles()->whereIn('id', [7, 8])->exists());
            $isAuthor = $user && ((int)$user->id === (int)$page->created_by);

            if ($isBoss) {
                // Hiển thị nút duyệt cho Sếp
                $alertHtml = "
                <div style='background:#fff3cd; color:#856404; padding:15px; border:1px solid #ffeeba; margin-bottom:20px; border-radius:4px; display:flex; justify-content:space-between; align-items:center;'>
                    <span>⚠️ <b>Lãnh đạo chú ý:</b> Văn bản này đang chờ phê duyệt.</span>
                    <form action='".url('/approve-content')."' method='POST' style='margin:0;'>
                        <input type='hidden' name='_token' value='".csrf_token()."'>
                        <input type='hidden' name='id' value='".$page->id."'>
                        <input type='hidden' name='type' value='page'>
                        <button type='submit' style='background:#28a745; color:white; border:none; padding:7px 15px; border-radius:4px; cursor:pointer; font-weight:bold;'>✅ PHÊ DUYỆT</button>
                    </form>
                </div>";
            } elseif ($isAuthor) {
                // Thông báo cho nhân viên
                $alertHtml = "<div style='background:#d1ecf1; color:#0c5460; padding:15px; border:1px solid #bee5eb; margin-bottom:20px; border-radius:4px;'>ℹ️ Báo cáo đã gửi đi và đang chờ Sếp duyệt.</div>";
            }
        }

        // --- RENDER NỘI DUNG ---
        $pageContent = (new PageContent($page));
        $page->html = $alertHtml . $pageContent->render(); // Chèn Alert vào đầu
        
        $sidebarTree = (new BookContents($page->book))->getTree();
        $commentTree = (new CommentTree($page));
        $nextPreviousLocator = new NextPreviousContentLocator($page, $sidebarTree);

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

    /**
     * Tạo bản sao (Copy) -> Cũng phải chờ duyệt
     */
    public function copy(Request $request, Cloner $cloner, string $bookSlug, string $pageSlug) {
        $page = $this->queries->findVisibleBySlugsOrFail($bookSlug, $pageSlug);
        $newParent = $page->getParent();
        $pageCopy = $cloner->clonePage($page, $newParent, $request->get('name') ?: $page->name);
        
        // KÍCH HOẠT QUY TRÌNH DUYỆT CHO BẢN COPY
        $pageCopy->syncApprovalStatus();

        return redirect($pageCopy->getUrl());
    }

    
    // Các hàm create, edit giữ nguyên như cũ hoặc gọi từ parent
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

    public function edit(Request $request, string $bookSlug, string $pageSlug) {
        $page = $this->queries->findVisibleBySlugsOrFail($bookSlug, $pageSlug);
        $editorData = new PageEditorData($page, $this->entityQueries, $request->query('editor', ''));
        return view('pages.edit', $editorData->getViewData());
    }
    /**
     * Show the move page view.
     */
    public function showMove(string $bookSlug, string $pageSlug)
    {
        $page = $this->pageRepo->getBySlug($bookSlug, $pageSlug);
        $this->checkOwnablePermission('page-update', $page);
        $this->checkOwnablePermission('page-delete', $page);

        return view('pages.move', [
            'book' => $page->book,
            'page' => $page,
        ]);
    }
    /**
     * Move the page to a new parent.
     * Xử lý hành động di chuyển trang.
     */
    public function move(Request $request, string $bookSlug, string $pageSlug)
    {
        // 1. Tìm trang cần di chuyển
        $page = $this->pageRepo->getBySlug($bookSlug, $pageSlug);
        
        // 2. Kiểm tra quyền
        $this->checkOwnablePermission('page-update', $page);
        $this->checkOwnablePermission('page-delete', $page);

        // 3. Lấy đích đến từ form gửi lên
        $entitySelection = $request->get('entity_selection', null);
        if ($entitySelection === null || $entitySelection === '') {
            return redirect()->back();
        }

        // 4. Gọi PageRepo để thực hiện di chuyển
        $this->pageRepo->move($page, $entitySelection);

        // 5. Chuyển hướng về trang sau khi di chuyển xong
        return redirect($page->getUrl());
    }
    /**
     * Show the delete page view.
     */
    /**
     * Show the delete page view.
     */
    public function showDelete(string $bookSlug, string $pageSlug)
    {
        // 1. Tìm trang theo Slug
        $page = $this->pageRepo->getBySlug($bookSlug, $pageSlug);
        
        // 2. Kiểm tra quyền xóa
        $this->checkOwnablePermission('page-delete', $page);

        // 3. THÊM DÒNG NÀY: Khai báo biến usedAsTemplate để tránh lỗi Undefined variable
        // Mặc định để là 0 (coi như không dùng làm template) để an toàn nhất
        $usedAsTemplate = 0; 

        // 4. Trả về giao diện kèm đầy đủ biến
        return view('pages.delete', [
            'book' => $page->book,
            'page' => $page,
            'usedAsTemplate' => $usedAsTemplate, // <--- QUAN TRỌNG
        ]);
    }
    /**
     * Remove the specified page from storage.
     * Xóa trang khỏi hệ thống.
     */
    public function destroy(string $bookSlug, string $pageSlug)
    {
        // 1. Dùng hàm getBySlug từ PageRepo (cái mà bạn đã sửa ở file kia)
        // Code cũ của bạn dùng $this->queries->findVisibleBySlugOrFail($pageSlug) bị thiếu tham số $bookSlug nên gây lỗi.
        $page = $this->pageRepo->getBySlug($bookSlug, $pageSlug);

        // 2. Kiểm tra quyền (Logic chặn xóa nếu không phải chủ Book)
        // Mình giữ lại logic kiểm tra quyền riêng của bạn ở đây
        $book = $page->book; 
        if (auth()->id() != $book->owned_by && !auth()->user()->hasSystemRole('admin')) {
             // Nếu không phải admin và không phải chủ sách thì chặn
             $this->checkOwnablePermission('page-delete', $page); 
        }

        // 3. Thực hiện xóa
        $this->pageRepo->destroy($page);

        // 4. Quay về trang chủ của cuốn sách
        return redirect($page->book->getUrl());
    }
}