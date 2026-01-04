<x-app-layout>
  <div class="max-w-5xl mx-auto p-6">
    <div class="flex items-center justify-between mb-4">
      <div>
        <h1 class="text-2xl font-bold">Report #{{ $report->id }}</h1>
        <div class="text-sm text-gray-600">
          {{ $report->created_at->format('d/m/Y H:i') }} — Status: {{ $report->status }}
        </div>
      </div>

      <div class="flex gap-3">
        <a class="px-3 py-2 rounded-lg border" href="{{ route('reports.index') }}">Retour</a>
        <a class="px-3 py-2 rounded-lg bg-black text-white"
           href="/api/reports/{{ $report->id }}/export.csv?token={{ $report->access_token }}">
          Export CSV
        </a>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow p-5">
      <table class="w-full text-sm border">
        <thead>
          <tr class="border-b bg-gray-50">
            <th class="p-2 text-left">Domaine</th>
            <th class="p-2">DA</th>
            <th class="p-2">PA</th>
            <th class="p-2">Ref. domains</th>
            <th class="p-2">Backlinks</th>
          </tr>
        </thead>
        <tbody>
          @foreach($report->items as $i)
            <tr class="border-b">
              <td class="p-2">{{ $i->domain }}</td>
              <td class="p-2 text-center">{{ $i->da }}</td>
              <td class="p-2 text-center">{{ $i->pa }}</td>
              <td class="p-2 text-center">{{ $i->linking_domains }}</td>
              <td class="p-2 text-center">{{ $i->inbound_links }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</x-app-layout>
