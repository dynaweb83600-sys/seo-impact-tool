<x-app-layout>
  <div class="max-w-5xl mx-auto p-6">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">Historique des reports</h1>
      <a href="/domain-checker" class="px-3 py-2 rounded-lg bg-black text-white">Nouveau report</a>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b">
          <tr>
            <th class="p-3 text-left">Date</th>
            <th class="p-3 text-left">Status</th>
            <th class="p-3 text-center">Domaines</th>
            <th class="p-3 text-center">Traités</th>
            <th class="p-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($reports as $r)
            <tr class="border-b">
              <td class="p-3">{{ $r->created_at->format('d/m/Y H:i') }}</td>
              <td class="p-3">{{ $r->status }}</td>
              <td class="p-3 text-center">{{ $r->requested_count }}</td>
              <td class="p-3 text-center">{{ $r->items()->count() }}</td>
              <td class="p-3 text-right">
                <a class="underline mr-3" href="{{ route('reports.show', $r) }}">Voir</a>
                <a class="underline"
                   href="/api/reports/{{ $r->id }}/export.csv?token={{ $r->access_token }}">
                   Export CSV
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td class="p-6 text-gray-500" colspan="5">Aucun report pour le moment.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="mt-4">{{ $reports->links() }}</div>
  </div>
</x-app-layout>
